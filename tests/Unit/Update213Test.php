<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update212;
use App\Console\Commands\Updates\Update213;
use PHPUnit\Framework\TestCase;

class Update213Test extends TestCase
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
        return (new Update213())->updateNginxConfig($config);
    }

    public function test_adds_every_set_once_per_server_block(): void
    {
        $updated = $this->update($this->config());

        foreach (['call_flows', 'music_on_hold', 'sip_status', 'registrations', 'system', 'number_translations', 'sip_profiles', 'gateways', 'bridges', 'access_controls'] as $legacy) {
            $this->assertSame(2, substr_count($updated, 'location ~ ^/app/'.$legacy.'/ {'), $legacy);
        }

        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/system-settings?tab=number_translations;'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/access-controls;'));
    }

    public function test_each_set_goes_to_its_own_insertion_point(): void
    {
        $updated = $this->update(self::SERVER);

        $this->assertMatchesRegularExpression(
            '/rewrite \^\.\*\/logout\.php\?\$ https:\/\/\$host\/logout permanent;\n        \}\n\n        # More legacy FusionPBX pages that now live in the FS PBX UI\n        location ~ \^\/app\/call_flows\//',
            $updated
        );
        $this->assertStringContainsString("}\n\n        #Implement Polycom log upload", $updated);
        $this->assertStringContainsString("}\n\n        location ~ \\.php$ {", $updated);
        $this->assertStringContainsString("error_page 404 /index.php;\n\n        # Legacy FusionPBX bridge and access control pages", $updated);
        $this->assertLessThan(
            strpos($updated, '#Implement Polycom log upload'),
            strpos($updated, 'location ~ ^/app/number_translations/')
        );
        $this->assertLessThan(
            strpos($updated, 'location ~ \\.php$ {'),
            strpos($updated, 'location ~ ^/app/gateways/')
        );
    }

    public function test_is_idempotent(): void
    {
        $once = $this->update($this->config());

        $this->assertSame($once, $this->update($once));
    }

    public function test_skips_a_set_whose_insertion_point_is_missing_and_adds_the_rest(): void
    {
        $config = str_replace("        #Implement Polycom log upload\n", "        # something else\n", $this->config());

        $updated = $this->update($config);

        $this->assertStringNotContainsString('^/app/number_translations/', $updated);
        $this->assertSame(2, substr_count($updated, 'location ~ ^/app/call_flows/ {'));
        $this->assertSame(2, substr_count($updated, 'location ~ ^/app/gateways/ {'));
    }

    public function test_leaves_an_unrelated_config_unchanged(): void
    {
        $config = "server {\n    listen 80;\n}\n";

        $this->assertSame($config, $this->update($config));
    }

    public function test_adds_only_the_sets_that_are_not_there_yet(): void
    {
        $once = $this->update($this->config());
        $partial = preg_replace('/        # Legacy FusionPBX number translations page.*?\n        \}\n/s', '', $once);

        $this->assertStringNotContainsString('^/app/number_translations/', $partial);

        $again = $this->update($partial);

        $this->assertSame(2, substr_count($again, 'location ~ ^/app/number_translations/ {'));
        $this->assertSame(2, substr_count($again, 'location ~ ^/app/call_flows/ {'));
    }

    public function test_works_together_with_update212_in_either_order(): void
    {
        $a = $this->update((new Update212())->updateNginxConfig($this->config()));
        $b = (new Update212())->updateNginxConfig($this->update($this->config()));

        $this->assertSame($a, $b);
        $this->assertSame(2, substr_count($a, 'location ~ ^/app/pin_numbers/ {'));
        $this->assertSame(2, substr_count($a, 'location ~ ^/app/bridges/ {'));
    }
}
