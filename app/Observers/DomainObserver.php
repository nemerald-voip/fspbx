<?php

namespace App\Observers;

use App\Models\Domain;
use App\Models\FusionCache;
use App\Models\DefaultSettings;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Session;
use App\Services\DialplanProvisioningService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class DomainObserver
{
    protected array $switchDirs = [];

    public function __construct(
        protected DialplanProvisioningService $dialplanProvisioningService,
        protected FreeswitchEslService $eslService,
    ) {}

    public function created(Domain $domain): void
    {
        try {
            $this->dialplanProvisioningService->bootstrapForDomain($domain);
        } catch (\Throwable $e) {
            logger('DomainObserver: failed to bootstrap dialplans for domain '
                . $domain->domain_name . ' - '
                . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }

        // directories should exist regardless of dialplan success
        $this->dialplanProvisioningService->ensureSwitchDirectories($domain->domain_name);

        try {
            FusionCache::flushAll();
        } catch (\Throwable $e) {
            logger('DomainObserver: failed to flush cache after domain create - '
                . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }

        $this->clearFusionSession();
    }


    protected function clearFusionSession(): void
    {
        // Laravel session
        Session::forget('domains');
        Session::forget('domain');
        Session::forget('switch');

        // FusionPBX legacy session variables
        if (isset($_SESSION)) {
            unset($_SESSION['domains']);
            unset($_SESSION['domain']);
            unset($_SESSION['switch']);
        }
    }

    /**
     * When a domain is deleted:
     */
    public function deleting(Domain $domain): void
    {
        $domainName = $domain->domain_name;
        $domainUuid = $domain->domain_uuid;

        if (empty($domainName) || empty($domainUuid)) {
            logger('DomainObserver.deleting: domain_name or domain_uuid missing. attrs=' . json_encode($domain->getAttributes()));
            return;
        }

        // 1) Delete related DB rows for this domain
        $this->cleanupDomainDatabase($domain);

        // 2) Load switch dirs from default_settings into our local map
        $this->loadSwitchDirs();

        // 3) Delete dialplan, directory, fax, recordings, voicemail, gateways, etc.
        $this->cleanupSwitchFilesForDomain($domainName);

        // 4) Reload XML via bgapi
        try {
            if ($this->eslService->isConnected()) {
                $result = $this->eslService->executeCommand('bgapi reloadxml');
            } else {
                logger('DomainObserver.deleted: ESL not connected, skipping reloadxml');
            }
        } catch (\Throwable $e) {
            logger(
                'DomainObserver.deleted: reloadxml bgapi failed: ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
            );
        }

        // 6) Flush cache
        FusionCache::flushAll();

        // 7) Clear session
        $this->clearFusionSession();
    }

    /**
     * Load the entire category 'switch' (only name=dir) into
     * a simple array: subcategory => value.
     */
    protected function loadSwitchDirs(): void
    {
        if (!empty($this->switchDirs)) {
            return;
        }

        $rows = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_name', 'dir')
            ->get();

        $dirs = [];

        foreach ($rows as $row) {
            if (!$row->default_setting_subcategory || !$row->default_setting_value) {
                continue;
            }

            // Simple mapping: subcategory -> path (trim trailing slash)
            $dirs[$row->default_setting_subcategory] = rtrim($row->default_setting_value, '/');
        }

        $this->switchDirs = $dirs;
    }

    /**
     * Helper to get a dir from the preloaded switch map.
     */
    protected function switchDir(string $subcategory): ?string
    {
        return $this->switchDirs[$subcategory] ?? null;
    }

    /**
     * Remove FreeSWITCH artifacts for the given domain name.
     */
    protected function cleanupSwitchFilesForDomain(string $domainName): void
    {
        $dialplanDir    = $this->switchDir('dialplan');
        $extensionsDir  = $this->switchDir('extensions');
        $storageDir     = $this->switchDir('storage');
        $recordingsDir  = $this->switchDir('recordings');
        $voicemailDir   = $this->switchDir('voicemail');
        $sipProfilesDir = $this->switchDir('sip_profiles');

        // --- Dialplan files & dirs ---
        if ($dialplanDir) {
            $base = $dialplanDir;

            $paths = [
                "$base/$domainName",
                "$base/$domainName.xml",
                "$base/public/$domainName",
                "$base/public/$domainName.xml",
            ];

            foreach ($paths as $path) {
                $this->deleteFileOrDirectory($path);
            }
        }

        // --- Directory / extensions ---
        if ($extensionsDir) {
            $base = $extensionsDir;

            $paths = [
                "$base/$domainName",
                "$base/$domainName.xml",
            ];

            foreach ($paths as $path) {
                $this->deleteFileOrDirectory($path);
            }
        }

        // --- Fax: storage/fax/<domain> ---
        if ($storageDir) {
            $faxPath = $storageDir . '/fax/' . $domainName;
            $this->deleteFileOrDirectory($faxPath);
        }

        // --- Gateways in sip_profiles: v_<domain>_*.xml ---
        if ($sipProfilesDir && File::isDirectory($sipProfilesDir)) {
            $needlePrefix = 'v_' . $domainName . '_';

            foreach (scandir($sipProfilesDir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (str_starts_with($file, $needlePrefix) && str_ends_with($file, '.xml')) {
                    $filePath = $sipProfilesDir . '/' . $file;
                    if (File::exists($filePath)) {
                        try {
                            File::delete($filePath);
                        } catch (\Throwable $e) {
                            logger('DomainObserver.cleanup: failed to delete sip_profile file ' . $filePath . ': ' .
                                $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                        }
                    }
                }
            }
        }

        // --- Recordings: /var/lib/freeswitch/recordings/<domain> ---
        if ($recordingsDir) {
            $recPath = $recordingsDir . '/' . $domainName;
            $this->deleteFileOrDirectory($recPath);
        }

        // --- Voicemail: /var/lib/freeswitch/storage/voicemail/default/<domain> ---
        if ($voicemailDir) {
            $vmPath = $voicemailDir . '/default/' . $domainName;
            $this->deleteFileOrDirectory($vmPath);
        }
    }

    /**
     * Delete either a file or directory (recursive), if it exists.
     */
    protected function deleteFileOrDirectory(string $path): void
    {
        if (!$path) {
            return;
        }

        try {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        } catch (\Throwable $e) {
            logger('DomainObserver.deleteFileOrDirectory: failed for ' . $path . ': ' .
                $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    protected function cleanupDomainDatabase(Domain $domain): void
    {
        foreach ($domain->cascadeRelations() as $relationName) {
            if (!method_exists($domain, $relationName)) {
                logger("DomainObserver.cleanupDomainDatabase: relation {$relationName} not found on Domain model");
                continue;
            }

            try {
                $rel = $domain->{$relationName}();

                if (!$rel instanceof Relation) {
                    logger("DomainObserver.cleanupDomainDatabase: {$relationName} did not return an Eloquent Relation");
                    continue;
                }

                $related = $rel->getRelated();
                $conn    = $related->getConnectionName(); // null => default
                $table   = $related->getTable();
                $pk      = $related->getKeyName();         // UUID PKs matter here

                // Skip missing table
                if (!Schema::connection($conn)->hasTable($table)) {
                    logger("DomainObserver.cleanupDomainDatabase: skipping {$relationName} (missing table {$table})");
                    continue;
                }

                // Savepoint per relation (prevents 25P02 poisoning the whole delete)
                DB::connection($conn)->transaction(function () use ($rel, $relationName, $pk) {
                    $rel->chunkById(200, function ($models) use ($relationName) {
                        $models->each(function ($model) use ($relationName) {
                            try {
                                $model->delete(); 
                            } catch (\Throwable $e) {
                                logger(
                                    "DomainObserver.cleanupDomainDatabase: failed deleting model from {$relationName}: "
                                    . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
                                );
                            }
                        });
                    }, $pk);
                });

            } catch (\Throwable $e) {
                logger(
                    "DomainObserver.cleanupDomainDatabase: error cleaning relation {$relationName}: "
                    . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
                );
            }
        }
    }
}
