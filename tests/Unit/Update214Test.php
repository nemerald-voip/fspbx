<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update212;
use App\Console\Commands\Updates\Update213;
use App\Console\Commands\Updates\Update214;
use PHPUnit\Framework\TestCase;

class Update214Test extends TestCase
{
    // A cut-down site file with the same insertion points as install/nginx_site_config.conf, twice.
    private const SERVER = <<<'NGINX'
server {
        listen 80;
        error_page 404 /index.php;

        # Rewrite logout URL
        location = /logout.php
        {
                rewrite ^.*/logout.php?$ https://$host/logout permanent;
        }

        # Rewrite login URL
        location = /login.php
        {
                rewrite ^.*/login.php?$ https://$host/login permanent;
        }

        #Implement Polycom log upload
        location /app/provision/logs {
        }

        #Implement Polycom Phone Configurations upload
        location /app/provision/phoneconfigs {
        }
        #Implement Polycom call logs upload
        location /app/provision/calls {
        }
        #Implement Polycom directory upload
        location /app/provision/directories {
        }

        #Implement Polycom core files upload
        location /app/provision/corefiles {
        }

        #REST api
        # if ($uri ~* ^.*/api/.*$) {
        # }

        #algo
        #mitel

        location ~ \.php$ {
                fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        }
}

NGINX;

    private function config(): string
    {
        return self::SERVER.self::SERVER;
    }

    private function update(string $config): string
    {
        return (new Update214())->updateNginxConfig($config);
    }

    public function test_adds_every_set_once_per_server_block(): void
    {
        $updated = $this->update($this->config());

        foreach (['log_viewer/', 'conferences/', 'conference_controls/', 'conference_profiles/', 'conferences_active/', 'destinations/', 'recordings/'] as $legacy) {
            $this->assertSame(2, substr_count($updated, 'location ~ ^/app/'.$legacy.' {'), $legacy);
        }

        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/logs?tab=freeswitch_logs;'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/active-conferences;'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/call-detail-records;'));
    }

    public function test_page_patterns_match_only_the_page_files(): void
    {
        $updated = $this->update(self::SERVER);

        $this->assertStringContainsString('location ~ ^/app/devices/(devices|device_edit|device_copy|device_imports)\.php$ {', $updated);
        $this->assertStringContainsString('location ~ ^/app/xml_cdr/(xml_cdr|xml_cdr_details|xml_cdr_search|xml_cdr_export)\.php$ {', $updated);
        $this->assertStringNotContainsString('^/app/xml_cdr/ {', $updated);
        $this->assertStringNotContainsString('xml_cdr_import', $updated);
        $this->assertStringNotContainsString('device_download', $updated);
        $this->assertStringNotContainsString('voicemail_imports', $updated);

        foreach (['devices/device_edit.php', 'devices/device_profile_copy.php', 'xml_cdr/xml_cdr_export.php', 'dialplans/dialplan_xml.php', 'voicemails/voicemail_message_toggle.php'] as $page) {
            $this->assertTrue($this->anyLocationMatches($updated, '/app/'.$page), $page.' should be redirected');
        }

        foreach (['devices/device_download.php', 'devices/device_vendors.php', 'xml_cdr/xml_cdr_import.php', 'xml_cdr/xml_cdr_statistics.php', 'voicemails/voicemail_imports.php'] as $page) {
            $this->assertFalse($this->anyLocationMatches($updated, '/app/'.$page), $page.' should not be redirected');
        }
    }

    public function test_each_set_goes_to_its_own_insertion_point(): void
    {
        $updated = $this->update(self::SERVER);

        $this->assertStringContainsString("}\n\n        #Implement Polycom Phone Configurations upload", $updated);
        $this->assertLessThan(
            strpos($updated, '#Implement Polycom Phone Configurations upload'),
            strpos($updated, 'location ~ ^/app/log_viewer/')
        );
        $this->assertLessThan(
            strpos($updated, '#Implement Polycom call logs upload'),
            strpos($updated, 'location ~ ^/app/conferences/')
        );
        $this->assertLessThan(
            strpos($updated, '#Implement Polycom directory upload'),
            strpos($updated, 'location ~ ^/app/destinations/')
        );
        $this->assertLessThan(
            strpos($updated, '#Implement Polycom core files upload'),
            strpos($updated, 'location ~ ^/app/dialplans/')
        );
        $this->assertLessThan(
            strpos($updated, 'location ~ \\.php$ {'),
            strpos($updated, 'location ~ ^/app/dialplans/'),
            'redirects must come before the .php handler'
        );
    }

    public function test_fax_and_call_center_sets_redirect_only_their_page_files(): void
    {
        $updated = $this->update(self::SERVER);

        $this->assertLessThan(strpos($updated, '#REST api'), strpos($updated, 'location ~ ^/app/fax/'));
        $this->assertLessThan(strpos($updated, '#algo'), strpos($updated, 'location ~ ^/app/call_centers/'));
        $this->assertStringContainsString('return 301 https://$host/basic-queues/agent-status;', $updated);
        $this->assertTrue($this->anyLocationMatches($updated, '/app/dialplan_outbound/dialplan_outbound_add.php'));
        $this->assertFalse($this->anyLocationMatches($updated, '/app/dialplan_inbound/dialplan_inbound_add.php'));

        foreach (['fax/fax_edit.php', 'fax/fax_outbox.php', 'call_centers/call_center_agents.php', 'call_centers/call_center_agent_status.php'] as $page) {
            $this->assertTrue($this->anyLocationMatches($updated, '/app/'.$page), $page.' should be redirected');
        }

        foreach (['fax/fax_emails.php', 'fax/fax_files_remote.php', 'fax_queue/fax_queue.php', 'call_centers/cmd.php'] as $page) {
            $this->assertFalse($this->anyLocationMatches($updated, '/app/'.$page), $page.' should not be redirected');
        }
    }

    public function test_is_idempotent(): void
    {
        $once = $this->update($this->config());

        $this->assertSame($once, $this->update($once));
    }

    public function test_skips_a_set_whose_insertion_point_is_missing_and_adds_the_rest(): void
    {
        $config = str_replace("        #Implement Polycom call logs upload\n", "        # something else\n", $this->config());

        $updated = $this->update($config);

        $this->assertStringNotContainsString('^/app/conferences/', $updated);
        $this->assertSame(2, substr_count($updated, 'location ~ ^/app/log_viewer/ {'));
        $this->assertSame(2, substr_count($updated, 'location ~ ^/app/destinations/ {'));
    }

    public function test_leaves_an_unrelated_config_unchanged(): void
    {
        $config = "server {\n    listen 80;\n}\n";

        $this->assertSame($config, $this->update($config));
    }

    public function test_adds_only_the_sets_that_are_not_there_yet(): void
    {
        $once = $this->update($this->config());
        $partial = preg_replace('/        # Legacy FusionPBX conference pages.*?\n        \}\n(?=\n)/s', '', $once);

        $this->assertStringNotContainsString('^/app/conferences/', $partial);

        $again = $this->update($partial);

        $this->assertSame(2, substr_count($again, 'location ~ ^/app/conference_controls/ {'));
        $this->assertSame(2, substr_count($again, 'location ~ ^/app/log_viewer/ {'));
    }

    public function test_works_together_with_update212_and_update213_in_any_order(): void
    {
        $u212 = new Update212();
        $u213 = new Update213();
        $u214 = new Update214();
        $config = $this->config();

        $a = $u214->updateNginxConfig($u213->updateNginxConfig($u212->updateNginxConfig($config)));
        $b = $u212->updateNginxConfig($u213->updateNginxConfig($u214->updateNginxConfig($config)));

        $this->assertSame(substr_count($a, 'location ~ ^/app/'), substr_count($b, 'location ~ ^/app/'));
        $this->assertSame(2, substr_count($a, 'location ~ ^/app/pin_numbers/ {'));
        $this->assertSame(2, substr_count($a, 'location ~ ^/app/log_viewer/ {'));
    }

    /** True when one of the redirect locations in the config would match the given path. */
    private function anyLocationMatches(string $config, string $path): bool
    {
        preg_match_all('/location ~ (\^\/app\/[^ ]+) \{/', $config, $matches);

        foreach ($matches[1] as $pattern) {
            if (preg_match('~'.str_replace('~', '\~', $pattern).'~', $path)) {
                return true;
            }
        }

        return false;
    }
}
