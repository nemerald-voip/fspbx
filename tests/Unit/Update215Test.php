<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update215;
use PHPUnit\Framework\TestCase;

class Update215Test extends TestCase
{
    // A cut-down site file: an HTTP server block and an HTTPS server block like the install template.
    private const SITE = <<<'NGINX'
server {
        listen 80;
        server_name *.us.domain.com;
}

server {
        listen 443 ssl;
        server_name *.us.domain.com;

        #ssl on;
        ssl_certificate /etc/nginx/ssl/fullchain.pem;
        ssl_certificate_key /etc/nginx/ssl/private/privkey.pem;
        ssl_protocols           TLSv1.2;
        ssl_ciphers             HIGH:!ADH:!MD5:!aNULL;
        #ssl_dhparam

        index index.php;
}

NGINX;

    private function update(string $config): string
    {
        return (new Update215())->updateNginxConfig($config);
    }

    public function test_replaces_the_tls_directives_with_the_snippet_include(): void
    {
        $updated = $this->update(self::SITE);

        $this->assertStringContainsString("        include snippets/fspbx-https.conf;\n", $updated);
        $this->assertStringNotContainsString('ssl_protocols', $updated);
        $this->assertStringNotContainsString('ssl_ciphers', $updated);
        $this->assertStringContainsString('ssl_certificate_key /etc/nginx/ssl/private/privkey.pem;', $updated);
    }

    public function test_keeps_the_include_where_the_directives_were(): void
    {
        $updated = $this->update(self::SITE);

        $this->assertLessThan(
            strpos($updated, '#ssl_dhparam'),
            strpos($updated, 'include snippets/fspbx-https.conf;')
        );
        $this->assertGreaterThan(
            strpos($updated, 'ssl_certificate_key'),
            strpos($updated, 'include snippets/fspbx-https.conf;')
        );
    }

    public function test_is_idempotent(): void
    {
        $once = $this->update(self::SITE);

        $this->assertSame($once, $this->update($once));
    }

    public function test_leaves_a_customised_site_file_alone(): void
    {
        $custom = str_replace('TLSv1.2;', 'TLSv1.2 TLSv1.3;', self::SITE);

        $this->assertSame($custom, $this->update($custom));
    }

    public function test_leaves_a_site_file_with_two_matches_alone(): void
    {
        $this->assertSame(self::SITE.self::SITE, $this->update(self::SITE.self::SITE));
    }

    public function test_leaves_an_unrelated_config_unchanged(): void
    {
        $config = "server {\n    listen 80;\n}\n";

        $this->assertSame($config, $this->update($config));
    }
}
