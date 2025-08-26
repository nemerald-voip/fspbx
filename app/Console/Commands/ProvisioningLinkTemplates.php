<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProvisioningLinkTemplates extends Command
{
    protected $signature = 'provisioning:link-templates {--force : Replace existing paths if needed}';
    protected $description = 'Create public symlinks for provisioning template folders (predefined list)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // Add more mappings here as needed
        $mappings = [
            [
                'target' => '/var/www/fspbx/resources/provisioning/algo/8186',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/algo/8186',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/algo/8188',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/algo/8188',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/algo/8189',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/algo/8189',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/algo/8196',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/algo/8196',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/avaya',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/avaya',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/cisco/8861',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/cisco/8861',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/cisco/9861',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/cisco/9861',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/fanvil/w611w',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/fanvil/w611w',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/C520',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/C520',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/C620',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/C620',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/D812',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/D812',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/D815',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/D815',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/D862',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/D862',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/D865',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/D865',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/snom/PA1plus',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/snom/PA1plus',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/grandstream/wp8x6',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/grandstream/wp8x6',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/grandstream/wp826',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/grandstream/wp826',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/yealink/ax83h',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/yealink/ax83h',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/yealink/t34w',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/yealink/t34w',
            ],
            [
                'target' => '/var/www/fspbx/resources/provisioning/yealink/w80',
                'link'   => '/var/www/fspbx/public/resources/templates/provision/yealink/w80',
            ],


        ];

        foreach ($mappings as $map) {
            $this->createSymlink($map['target'], $map['link'], $force);
        }

        return self::SUCCESS;
    }

    protected function createSymlink(string $target, string $link, bool $force): void
    {
        // Validate target existence
        if (!File::exists($target) || !File::isDirectory($target)) {
            $this->warn("Skip: target not found or not a directory: $target");
            return;
        }

        // Ensure parent directory for the link exists
        File::ensureDirectoryExists(dirname($link), 0755, true);

        // If correct symlink already exists, done
        if (is_link($link) && readlink($link) === $target) {
            $this->info("OK: symlink exists -> $link -> $target");
            return;
        }

        // If something exists at link path
        if (File::exists($link) || is_link($link)) {
            if (!$force) {
                $this->warn("Exists: $link (use --force to replace). Skipping.");
                return;
            }
            // Remove existing file/dir/symlink
            if (is_link($link) || File::isFile($link)) {
                File::delete($link);
            } elseif (File::isDirectory($link)) {
                File::deleteDirectory($link);
            }
        }

        if (@symlink($target, $link) === false) {
            $this->error("Failed: symlink $link -> $target");
            return;
        }

        $this->info("Created: $link -> $target");
    }
}
