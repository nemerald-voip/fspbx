<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallLetsEncryptCertificate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install-lets-encrypt-certificate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Let\'s Encrypt SSL using Dehydrated for FS PBX';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domain = $this->ask('Enter the domain for SSL (e.g., us.domain.com)');
        if (!$domain) {
            $this->error('Domain is required.');
            return;
        }

        $dehydratedDir = "/etc/dehydrated";
        $wellKnownDir = "/var/www/dehydrated";
        $certsDir = "/etc/dehydrated/certs/$domain";
        $nginxConf = "/etc/nginx/sites-available/fspbx.conf";

        // Install Dehydrated
        $this->info("Installing Dehydrated...");
        shell_exec("apt install -y dehydrated curl");

        // Configure Dehydrated
        $this->info("Configuring Dehydrated...");
        shell_exec("mkdir -p $dehydratedDir");
        file_put_contents("$dehydratedDir/domains.txt", "$domain\n");

        // Ensure WELLKNOWN directory exists
        if (!is_dir($wellKnownDir)) {
            $this->info("Creating WELLKNOWN directory...");
            shell_exec("mkdir -p $wellKnownDir");
            shell_exec("chown -R www-data:www-data $wellKnownDir");
            shell_exec("chmod -R 755 $wellKnownDir");
        }

        file_put_contents(
            "$dehydratedDir/config",
            <<<EOF
BASEDIR=$dehydratedDir
WELLKNOWN=/var/www/dehydrated
EOF
        );

        file_put_contents("$dehydratedDir/hook.sh", "#!/bin/bash\nexit 0;\n");
        shell_exec("chmod +x $dehydratedDir/hook.sh");

        // Generate SSL certificate
        $this->info("Registering account and generating SSL certificate...");
        shell_exec("dehydrated --register --accept-terms");
        shell_exec("dehydrated -c");

        if (!file_exists("$certsDir/fullchain.pem")) {
            $this->error("Error: Certificate generation failed!");
            return;
        }

        // Update Nginx Configuration
        $this->info("Updating Nginx configuration...");

        // Read the existing configuration
        $nginxConfig = file_get_contents($nginxConf);

        // Replace old certificate paths with new ones
        $updatedConfig = preg_replace(
            [
                '/ssl_certificate\s+\/etc\/nginx\/ssl\/fullchain.pem;/',
                '/ssl_certificate_key\s+\/etc\/nginx\/ssl\/private\/privkey.pem;/'
            ],
            [
                "ssl_certificate $certsDir/fullchain.pem;",
                "ssl_certificate_key $certsDir/privkey.pem;"
            ],
            $nginxConfig
        );

        // Save updated configuration
        file_put_contents($nginxConf, $updatedConfig);

        $this->info("Restarting Nginx...");
        shell_exec("systemctl restart nginx");

        // Set up auto-renewal
        $this->info("Setting up auto-renewal...");
        shell_exec("(crontab -l 2>/dev/null; echo \"0 3 * * * dehydrated -c && systemctl reload nginx\") | crontab -");

        $this->info("SSL setup complete for $domain using Dehydrated!");
    }
}
