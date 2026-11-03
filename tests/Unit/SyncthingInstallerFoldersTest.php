<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SyncthingInstallerFoldersTest extends TestCase
{
    private string $root;
    private string $folder;

    protected function setUp(): void
    {
        $jq = new Process(['bash', '-c', 'command -v jq']);
        $jq->run();
        if (! $jq->isSuccessful()) {
            $this->markTestSkipped('jq is required to test the Syncthing installer.');
        }

        $this->root = sys_get_temp_dir().'/syncthing-folders-'.bin2hex(random_bytes(8));
        $this->folder = $this->root.'/sync folder';
        mkdir($this->folder, 0700, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            (new Filesystem())->deleteDirectory($this->root);
        }
    }

    private function seedFolders(bool $includeId = true): void
    {
        foreach (['local', 'remote'] as $side) {
            $folder = [
                'path' => '/old/path', 'label' => 'Old label', 'rescanIntervalS' => 60,
                'devices' => [['deviceID' => 'OTHER', 'introducedBy' => 'KEEP']],
                'versioning' => ['type' => 'simple', 'params' => ['keep' => '5']],
                'ignorePerms' => true,
            ];
            if ($includeId) {
                $folder['id'] = md5($this->folder);
            }
            file_put_contents($this->root.'/'.$side.'.json', json_encode($folder, JSON_THROW_ON_ERROR));
        }
    }

    private function runFolderSetup(string $side = '', string $failure = ''): Process
    {
        $installer = file_get_contents(__DIR__.'/../../install/install_syncthing.sh');
        [$definitions, $run] = explode('# ========= Run =========', $installer, 2);
        [$cleanup] = explode('require_root', $run, 2);
        [, $folderSetup] = explode('# Create & share folders both ways', $run, 2);

        // Run the real folder loop and jq filters; replace host/REST operations.
        // The installer bootstrap, SSH, services, and live folders are never run.
        $mocks = <<<'BASH'

SYNC_FOLDERS=("$TEST_FOLDER|3600")
LOCAL_ID=LOCAL
PEER_ID=REMOTE
api_key(){ echo test-key; }
remote_api_key(){ echo test-key; }
chown(){ :; }
sudo(){ [[ "$TEST_SIDE:$TEST_FAILURE" != 'local:mkdir' ]]; }
ssh_run(){
  if [[ "$1" == *'mkdir -p'* && "$TEST_SIDE:$TEST_FAILURE" == 'remote:mkdir' ]]; then
    return 1
  fi
}
spin_start(){ echo spinner-start >> "$TEST_ROOT/events"; }
spin_stop(){ echo spinner-stop >> "$TEST_ROOT/events"; }
stop_remote_tunnel(){ echo tunnel-stop >> "$TEST_ROOT/events"; }
restart_st(){ echo restart >> "$TEST_ROOT/events"; }
api_post(){ :; }
r_api_post(){ :; }
get_folder(){
  local side="$1"
  if [[ "$TEST_SIDE:$TEST_FAILURE" == "$side:invalid-share" && -f "$TEST_ROOT/$side.put" ]]; then
    echo '[]'
  elif [[ -f "$TEST_ROOT/$side.json" ]]; then
    cat "$TEST_ROOT/$side.json"
  else
    return 22
  fi
}
put_folder(){
  local side="$1" endpoint="$2" json="$3"
  echo "$side $endpoint" >> "$TEST_ROOT/puts"
  if [[ "$TEST_SIDE:$TEST_FAILURE" == "$side:save" ]]; then return 22; fi
  if [[ "$TEST_SIDE:$TEST_FAILURE" == "$side:share" && -f "$TEST_ROOT/$side.put" ]]; then return 22; fi
  printf '%s\n' "$json" > "$TEST_ROOT/$side.json"
  touch "$TEST_ROOT/$side.put"
}
api_get(){ get_folder local; }
r_api_get(){ get_folder remote; }
api_put(){ put_folder local "$1" "$2"; }
r_api_put(){ put_folder remote "$1" "$2"; }

BASH;
        foreach (['events', 'puts', 'local.put', 'remote.put'] as $file) {
            if (is_file($this->root.'/'.$file)) {
                unlink($this->root.'/'.$file);
            }
        }

        $process = new Process(['bash', '-c', $definitions.$mocks.$cleanup.$folderSetup], null, [
            'TEST_ROOT' => $this->root,
            'TEST_FOLDER' => $this->folder,
            'TEST_SIDE' => $side,
            'TEST_FAILURE' => $failure,
        ], null, 10);
        $process->run();

        return $process;
    }

    /** @dataProvider folderStates */
    public function test_folders_are_created_updated_and_shared_on_both_servers(string $state): void
    {
        if ($state !== 'new') {
            $this->seedFolders($state !== 'missing-id');
        }

        // A second run must preserve settings and must not duplicate the peer.
        for ($run = 0; $run < 2; $run++) {
            $process = $this->runFolderSetup();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('', $process->getErrorOutput());
            $this->assertStringContainsString('Folders are now shared on both servers.', $process->getOutput());
            foreach (['local' => 'REMOTE', 'remote' => 'LOCAL'] as $side => $peer) {
                $folder = json_decode(file_get_contents($this->root.'/'.$side.'.json'), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame(md5($this->folder), $folder['id']);
                $this->assertSame($this->folder, $folder['path']);
                $this->assertSame($this->folder, $folder['label']);
                $this->assertSame(3600, $folder['rescanIntervalS']);
                $expectedDevices = [['deviceID' => $peer]];
                if ($state !== 'new') {
                    array_unshift($expectedDevices, ['deviceID' => 'OTHER', 'introducedBy' => 'KEEP']);
                    $this->assertSame(['type' => 'simple', 'params' => ['keep' => '5']], $folder['versioning']);
                    $this->assertTrue($folder['ignorePerms']);
                } else {
                    $this->assertSame('basic', $folder['filesystemType']);
                    $this->assertSame('sendreceive', $folder['type']);
                }
                $this->assertSame($expectedDevices, $folder['devices']);
            }
        }
    }

    public static function folderStates(): array
    {
        return [['new'], ['existing'], ['missing-id']];
    }

    /** @dataProvider folderFailures */
    public function test_folder_failures_stop_setup_without_reporting_success(string $side, string $failure, string $message): void
    {
        $this->seedFolders();
        if ($failure === 'json') {
            // Valid JSON, but jq cannot update a folder object from this value.
            file_put_contents($this->root.'/'.$side.'.json', '[1]');
        }
        $process = $this->runFolderSetup($side, $failure);

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString($message, $process->getErrorOutput());
        $this->assertStringNotContainsString('Folders are now shared', $process->getOutput());
        $this->assertStringNotContainsString('Syncthing installed;', $process->getOutput());
        $events = file_get_contents($this->root.'/events');
        $this->assertStringContainsString('spinner-stop', $events);
        $this->assertStringContainsString('tunnel-stop', $events);
        $this->assertStringNotContainsString('restart', $events);
        if (in_array($failure, ['json', 'mkdir'], true)) {
            $puts = is_file($this->root.'/puts') ? file_get_contents($this->root.'/puts') : '';
            $this->assertStringNotContainsString($side.' config/folders/', $puts);
        }
    }

    public static function folderFailures(): array
    {
        $cases = [];
        foreach (['local', 'remote'] as $side) {
            foreach (['json' => "Failed to prepare $side folder", 'save' => "Failed to save $side folder",
                'share' => "Failed to share $side folder", 'invalid-share' => "Invalid $side folder",
                'mkdir' => "Failed to create $side folder"] as $failure => $message) {
                $cases[$side.' '.$failure] = [$side, $failure, $message];
            }
        }

        return $cases;
    }
}
