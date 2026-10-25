<?php

namespace App\Console\Commands\Updates;

use Symfony\Component\Process\Process;
use Throwable;

class Update214
{
    private const VERSION = '2.1.4';
    private const NGINX_CONFIGS = [
        '/etc/nginx/sites-available/fspbx.conf',
        '/etc/nginx/sites-enabled/fspbx.conf',
    ];

    // Where each set of redirect locations goes: just before one of the Polycom upload
    // comments. The install template has each comment once in each of its two server
    // blocks, so a site file has two of each.
    private const BEFORE_POLYCOM_PHONECONFIGS = "        #Implement Polycom Phone Configurations upload\n";
    private const BEFORE_POLYCOM_CALLS = "        #Implement Polycom call logs upload\n";
    private const BEFORE_POLYCOM_DIRECTORIES = "        #Implement Polycom directory upload\n";
    private const BEFORE_POLYCOM_COREFILES = "        #Implement Polycom core files upload\n";

    // Path after /app/ (a regular expression) => FS PBX page that replaced it. The sets
    // mirror the redirect blocks in install/nginx_site_config.conf, one set per
    // insertion point. Only page files are matched where the legacy folder also holds
    // scripts or pages that have no replacement yet.
    private const SETS = [
        [
            'marker' => 'Legacy FusionPBX log viewer page that now lives in the FS PBX Logs page',
            'anchor' => self::BEFORE_POLYCOM_PHONECONFIGS,
            'redirects' => [
                'log_viewer/' => 'logs?tab=freeswitch_logs',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX conference pages that now live in the FS PBX UI',
            'anchor' => self::BEFORE_POLYCOM_CALLS,
            'redirects' => [
                'conferences/' => 'conferences',
                'conference_controls/' => 'conference-controls',
                'conference_profiles/' => 'conference-profiles',
                'conferences_active/' => 'active-conferences',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX phone number, recording and device pages that now live in the FS PBX UI',
            'anchor' => self::BEFORE_POLYCOM_DIRECTORIES,
            'redirects' => [
                'destinations/' => 'phone-numbers',
                'recordings/' => 'recordings',
                'devices/(devices|device_edit|device_copy|device_imports)\\.php$' => 'devices',
                'devices/(device_profiles|device_profile_edit|device_profile_copy)\\.php$' => 'device-profiles',
            ],
        ],
        [
            'marker' => 'Legacy FusionPBX dialplan, voicemail and call history pages that now live in the FS PBX UI',
            'anchor' => self::BEFORE_POLYCOM_COREFILES,
            'redirects' => [
                'dialplans/(dialplans|dialplan_add|dialplan_edit|dialplan_xml)\\.php$' => 'dialplans',
                'voicemails/(voicemails|voicemail_edit|voicemail_messages|voicemail_message_toggle)\\.php$' => 'voicemails',
                'xml_cdr/(xml_cdr|xml_cdr_details|xml_cdr_search|xml_cdr_export)\\.php$' => 'call-detail-records',
            ],
        ],
    ];

    public function apply(): bool
    {
        try {
            $path = $this->nginxConfigPath();

            if ($path === null) {
                echo "FS PBX Nginx site not found. Skipping legacy page redirects.\n";

                return true;
            }

            $config = file_get_contents($path);

            if ($config === false) {
                throw new \RuntimeException('Unable to read '.$path.'.');
            }

            $updated = $this->updateNginxConfig($config);

            if ($updated === $config) {
                echo "Nginx already has these legacy page redirects, or the site file has none of the places they are added. No change.\n";

                return true;
            }

            $this->writeFile($path.'.bak-'.self::VERSION, $config);
            $this->writeFile($path, $updated);

            if (! $this->runProcess(['nginx', '-t'])) {
                $this->writeFile($path, $config);
                echo "Nginx validation failed. Restored the existing site configuration.\n";

                // Cosmetic change: do not block the rest of the update.
                return true;
            }

            if (! $this->runProcess(['systemctl', 'reload', 'nginx'])) {
                echo "Nginx could not be reloaded. The redirects will apply after the next restart.\n";
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }

    /**
     * Add every redirect set whose marker is not in the config yet, at its own
     * insertion point. A set whose insertion point is missing (a customised
     * site file) is skipped; the others are still added.
     */
    public function updateNginxConfig(string $config): string
    {
        foreach (self::SETS as $set) {
            if (str_contains($config, '# '.$set['marker'])) {
                continue;
            }

            if (! str_contains($config, $set['anchor'])) {
                continue;
            }

            $block = $this->redirectBlock($set['marker'], $set['redirects']);

            $config = str_replace($set['anchor'], $block."\n".$set['anchor'], $config);
        }

        return $config;
    }

    private function redirectBlock(string $marker, array $redirects): string
    {
        $block = '        # '.$marker."\n";

        foreach ($redirects as $legacy => $target) {
            $block .= '        location ~ ^/app/'.$legacy." {\n";
            $block .= '                return 301 https://$host/'.$target.";\n";
            $block .= "        }\n";
        }

        return $block;
    }

    private function nginxConfigPath(): ?string
    {
        foreach (self::NGINX_CONFIGS as $candidate) {
            if (is_file($candidate)) {
                // Follow the sites-enabled symlink so the real file is edited.
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write '.$path.'.');
        }
    }

    private function runProcess(array $command): bool
    {
        try {
            $process = new Process($command, null, null, null, 60);
            $process->run(function (string $type, string $buffer) {
                echo $buffer;
            });

            return $process->isSuccessful();
        } catch (Throwable $exception) {
            echo 'Warning: Unable to run '.implode(' ', $command).': '.$exception->getMessage()."\n";

            return false;
        }
    }
}
