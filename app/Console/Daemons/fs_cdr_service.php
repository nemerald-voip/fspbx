#!/usr/bin/env php
<?php
/**
 * FS PBX CDR Ingest Daemon (Supervisor-friendly)
 * - Continuous import with batching and short sleeps
 * - Graceful shutdown on SIGTERM/SIGINT
 * - File stability checks to avoid partial reads
 * - Light DB reconnect loop (like FusionPBX)
 * - STDERR logging (captured by Supervisor)
 */

if (!defined('STDIN')) {
    // CLI only
    exit;
}

// Resolve project root relative to this file (…/fspbx)
$PROJECT_ROOT = dirname(__DIR__, 3);

// fwrite(STDERR, "[fs_cdr_service] PROJECT_ROOT = {$PROJECT_ROOT}\n");

$FILE_REQUIRE = $PROJECT_ROOT . '/public/resources/require.php';
if (!file_exists($FILE_REQUIRE)) {
    fwrite(STDERR, "[fs_cdr_service] ERROR: require.php not found: {$FILE_REQUIRE}\n");
    exit(1);
}
require_once $FILE_REQUIRE;

$FILE_REQUIRE = __DIR__ . DIRECTORY_SEPARATOR . 'cdr_import.php';
if (!file_exists($FILE_REQUIRE)) {
    fwrite(STDERR, "[fs_cdr_service] ERROR: cdr_import.php not found: {$FILE_REQUIRE}\n");
    exit(1);
}
require_once $FILE_REQUIRE;

// Runtime limits
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

// Optional CLI query-string: php fs_cdr_service.php "debug=1&batch=100&sleep_us=100000&max_bytes=3145728"
$script_name = $argv[0] ?? 'fs_cdr_service.php';
if (!empty($argv[1])) {
    parse_str($argv[1], $_GET);
}
$hostname    = isset($_GET['hostname']) ? urldecode($_GET['hostname']) : gethostname();
$debug       = isset($_GET['debug']) ? (int)$_GET['debug'] : 0;
$debug_level = (string)$debug; // to satisfy any checks that expect a string

// Tunables (env overrides allowed)
$BATCH_LIMIT = (int)($_GET['batch']     ?? getenv('XML_CDR_BATCH')     ?: 100);
$SLEEP_US    = (int)($_GET['sleep_us']  ?? getenv('XML_CDR_SLEEP_US')  ?: 100000); // 100ms main loop sleep
$STABLE_US   = (int)($_GET['stable_us'] ?? getenv('XML_CDR_STABLE_US') ?: 10000);  // 10ms file-stability recheck
$MAX_BYTES   = (int)($_GET['max_bytes'] ?? getenv('XML_CDR_MAX_BYTES') ?: 3*1024*1024);

// Graceful shutdown
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
    pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });
}

// global $database;
$database = new database;
$settings = null;
$logErr = function (string $msg) { fwrite(STDERR, "[fs_cdr_service] {$msg}\n"); };


$setting = new settings();
$xml_cdr_dir = $setting->get('switch', 'log').'/xml_cdr';

// migrate old failed dir naming if present
if (file_exists($xml_cdr_dir.'/failed/invalid_xml')) {
    @rename($xml_cdr_dir.'/failed/invalid_xml', $xml_cdr_dir.'/failed/xml');
}
@mkdir($xml_cdr_dir.'/failed/xml',  0770, true);
@mkdir($xml_cdr_dir.'/failed/size', 0770, true);
@mkdir($xml_cdr_dir.'/failed/sql',  0770, true);

// Fix wrong perms if needed (best-effort)
if (file_exists($xml_cdr_dir.'/failed')) {
    @exec('chmod 770 -R ' . escapeshellarg($xml_cdr_dir.'/failed'));
}

// Importer from FusionPBX
$cdr = new cdr_import();

if ($debug) {
    $logErr("Start PID=" . getmypid()
        . " Host={$hostname}"
        . " Dir={$xml_cdr_dir}"
        . " Batch={$BATCH_LIMIT}"
        . " SleepUs={$SLEEP_US}"
        . " MaxBytes={$MAX_BYTES}");
}

// Main loop
while ($running) {
    // Keep DB connected (handles restarts)
    // if (!$database->is_connected()) {
    //     $connectDatabase();
    // }

    // List a batch
    $files = array_slice(glob($xml_cdr_dir . '/*.cdr.xml') ?: [], 0, $BATCH_LIMIT);

    if (!empty($files)) {
        foreach ($files as $file) {
            if (!$running) break;

            // size checks
            $size = @filesize($file);
            if ($size === false || $size === 0 || $size >= $MAX_BYTES) {
                if ($debug) $logErr("Move oversized/zero {$file} -> failed/size");
                @rename($file, $xml_cdr_dir.'/failed/size/'.basename($file));
                continue;
            }

            // file stability (avoid partial reads)
            $s1 = $size;
            usleep($STABLE_US);
            $s2 = @filesize($file);
            if ($s1 !== $s2) {
                // try next cycle
                continue;
            }

            // read
            $content = @file_get_contents($file);
            if ($content === false) {
                if ($debug) $logErr("Move unreadable {$file} -> failed/xml");
                @rename($file, $xml_cdr_dir.'/failed/xml/'.basename($file));
                continue;
            }

            // decode if url-encoded
            if ($content !== '' && $content[0] === '%') {
                $content = urldecode($content);
            }

            $base = basename($file);
            $cdr->file = $base;
            $leg = (substr($base, 0, 2) === 'a_') ? 'a' : 'b';

            try {
                // original passed undefined $i; 0 is fine
                $cdr->xml_array(0, $leg, $content);

                // success
                @unlink($file);
                if ($debug > 1) $logErr("Imported {$base}");
            } catch (Throwable $e) {
                // parser/SQL errors -> failed/sql
                @rename($file, $xml_cdr_dir.'/failed/sql/'.basename($file));
                $logErr("ERROR importing {$base}: " . $e->getMessage());
            }
        }
    }

    usleep($SLEEP_US);

    if ($debug > 1) {
        $mem  = round(memory_get_usage() / 1024) . ' KB';
        $peak = round(memory_get_peak_usage() / 1024) . ' KB';
        $logErr("Mem current={$mem} peak={$peak}");
    }
}

$logErr("Stopping (signal received)");
exit(0);
