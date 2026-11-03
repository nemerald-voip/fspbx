<?php

namespace App\Console\Commands;

use App\Services\FrontendAssets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

class InstallFrontendAssets extends Command
{
    protected $signature = 'frontend:install
        {--prepare : Download and verify assets without activating them}
        {--activate : Activate the release already prepared for this checkout}
        {--archive= : Install a locally supplied release archive instead of downloading}';

    protected $description = 'Install matching prebuilt frontend assets without running npm';

    public function handle(FrontendAssets $assets): int
    {
        $temporary = null;
        try {
            if ($this->option('prepare') && $this->option('activate')) throw new RuntimeException('Choose either --prepare or --activate.');
            $version = (require base_path('config/version.php'))['release'];
            if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) throw new RuntimeException('Invalid application release version.');
            if (!$this->option('activate')) {
                try {
                    $temporary = storage_path('app/frontend/download-'.bin2hex(random_bytes(8)));
                    $this->prepareRelease($assets, $version, $temporary);
                    $this->info('Frontend release verified and staged.');
                } catch (\Throwable $error) {
                    // Unpublished development checkouts can reuse an exact,
                    // explicitly built local package. Never compile on failure.
                    if ($this->option('archive') || !$assets->prepareExistingLocal()) throw $error;
                    $this->warn('Released assets unavailable: '.$error->getMessage());
                    $this->info('Using the verified existing local build for this checkout. No build was started.');
                }
            }
            if (!$this->option('prepare')) {
                $origin = $assets->prepared()['metadata']['origin'] ?? null;
                if ($origin === 'release' && ($assets->state()['core']['metadata']['origin'] ?? null) === 'local') {
                    $this->warn('Replacing the locally compiled frontend with released assets.');
                }
                $assets->activatePrepared();
                $this->info($origin === 'release' ? 'Released frontend assets activated.' : 'Verified local frontend assets retained.');
                foreach (Module::allEnabled() as $module) {
                    $name = $module->getName();
                    if (!is_dir($module->getPath().'/Resources/assets/js/Pages')) continue;
                    if (!isset($assets->browserConfiguration([$name])['modules'][$name])) {
                        $this->warn($name.': compatible frontend assets are unavailable. Resolve the module refresh warnings and retry php artisan modules:refresh. For a local build, run npm run build.');
                    }
                }
                if (is_file(storage_path('vite.hot'))) {
                    $this->warn('A Vite development hot file is present; browsers still use that development server until it is stopped.');
                }
            }
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Frontend installation failed: '.$error->getMessage());
            $this->line('No npm build was started. For a development checkout or local patch, use npm run build.');
            return self::FAILURE;
        } finally {
            if ($temporary) $assets->removeDirectory($temporary);
        }
    }

    private function prepareRelease(FrontendAssets $assets, string $version, string $temporary): void
    {
        if (!mkdir($temporary, 0755, true) && !is_dir($temporary)) {
            throw new RuntimeException('Unable to create frontend staging directory.');
        }
        $archive = $this->option('archive');
        if (!$archive) {
            $filename = 'fspbx-frontend-'.$version.'.tar.gz';
            $url = 'https://github.com/nemerald-voip/fspbx/releases/download/v'.$version.'/'.$filename;
            $this->info('Downloading frontend assets for FS PBX '.$version.'...');
            $checksum = Http::connectTimeout(15)->timeout(30)->get($url.'.sha256')->throw()->body();
            if (!preg_match('/^([a-f0-9]{64})(?:\s|$)/D', trim($checksum), $matches)) {
                throw new RuntimeException('Invalid release checksum.');
            }
            $archive = $temporary.'/'.$filename;
            Http::connectTimeout(15)->timeout(180)->withOptions([
                'sink' => $archive,
                'progress' => static function ($total, $downloaded): void {
                    if ($total > 268435456 || $downloaded > 268435456) {
                        throw new RuntimeException('Frontend archive is too large.');
                    }
                },
            ])->get($url)->throw();
            if (!hash_equals($matches[1], hash_file('sha256', $archive))) {
                throw new RuntimeException('Frontend release archive checksum failed.');
            }
        }
        $archive = realpath($archive);
        if (!$archive || !is_file($archive)) throw new RuntimeException('Frontend release archive is missing.');
        $assets->extract($archive, $temporary.'/package');
        $assets->prepare($temporary.'/package', $version);
    }
}
