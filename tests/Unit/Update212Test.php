<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update212;
use PHPUnit\Framework\TestCase;

class Update212Test extends TestCase
{
    private const CONFIG = <<<'NGINX'
server {
        listen 80;
        # Rewrite login URL
        location = /login.php
        {
                rewrite ^.*/login.php?$ https://$host/login permanent;
        }

        # Rewrite domain change URL
}
server {
        listen 443 ssl;
        # Rewrite login URL
        location = /login.php
        {
                rewrite ^.*/login.php?$ https://$host/login permanent;
        }

        # Rewrite dashboard URL
}

NGINX;

    public function test_adds_redirects_after_every_login_block(): void
    {
        $updated = (new Update212())->updateNginxConfig(self::CONFIG);

        $this->assertSame(2, substr_count($updated, 'location ~ ^/app/pin_numbers/ {'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/email-templates;'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/vars;'));
        $this->assertSame(2, substr_count($updated, 'return 301 https://$host/modules;'));
        $this->assertStringContainsString("}\n\n        # Legacy FusionPBX pages", $updated);
        $this->assertStringContainsString("}\n\n        # Rewrite domain change URL", $updated);
    }

    public function test_is_idempotent(): void
    {
        $update = new Update212();
        $once = $update->updateNginxConfig(self::CONFIG);

        $this->assertSame($once, $update->updateNginxConfig($once));
    }

    public function test_leaves_config_without_login_block_unchanged(): void
    {
        $config = "server {\n    listen 80;\n}\n";

        $this->assertSame($config, (new Update212())->updateNginxConfig($config));
    }
}
