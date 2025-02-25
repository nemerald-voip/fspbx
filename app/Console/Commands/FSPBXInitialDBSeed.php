<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\UserGroup;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FSPBXInitialDBSeed extends Command
{
    protected $signature = 'fspbx:initial-seed';
    protected $description = 'Seed DB after initial FS PBX installation';

    public function handle()
    {
        $this->info('Seeding Database ...');

        // Step 1: Run Upgrade Schema
        $this->runUpgradeSchema();

        // Step 2: Create the Admin Domain
        $domain = Domain::firstOrCreate(
            ['domain_name' => 'admin.localhost'],
            ['domain_description' => 'Admin Domain', 'domain_enabled' => true]
        );

        $this->info("Domain 'admin.localhost' created successfully.");

        // Step 3: Run Upgrade Domains after creating domain
        $this->runUpgradeDomains();

        // Step 4: Create or Update Superadmin User
        $username = "admin@fspbx.net";
        $password = Str::random(25);

        $user = User::where('username', $username)->first();

        if ($user) {
            // Update existing user's password
            $user->update(['password' => Hash::make($password)]);
            $this->info("Superadmin user '$username' already exists. Password updated.");
        } else {
            // Create new Superadmin user
            $user = User::create([
                'domain_uuid' => $domain->domain_uuid,
                'username' => $username,
                'password' => Hash::make($password),
                'user_enabled' => true,
            ]);
            $this->info("Superadmin user '$username' created successfully.");
        }

        // Step 5: Assign User to Superadmin Group
        $group = Groups::where('group_name', 'superadmin')->first();
        if (!$group) {
            $this->error("Superadmin group not found. Please check your database.");
            return 1;
        }

        if (!UserGroup::where('user_uuid', $user->user_uuid)->where('group_name', 'superadmin')->exists()) {
            UserGroup::create([
                'user_group_uuid' => Str::uuid(),
                'domain_uuid' => $domain->domain_uuid,
                'group_name' => 'superadmin',
                'group_uuid' => $group->group_uuid,
                'user_uuid' => $user->user_uuid,
            ]);
            $this->info("User assigned to Superadmin group successfully.");
        } else {
            $this->info("User is already assigned to the Superadmin group.");
        }

        // Step 6: Run Upgrade Defaults
        $this->runUpgradeDefaults();

        // Step 7: Run Additional Laravel Commands
        $this->info("Caching configuration...");
        Artisan::call('config:cache');

        $this->info("Caching routes...");
        Artisan::call('route:cache');

        $this->info("Restarting queue workers...");
        Artisan::call('queue:restart');

        // Step 8: Run Laravel Migrations
        $this->info("Running database migrations...");
        Artisan::call('migrate', ['--force' => true]); // --force to prevent confirmation prompt
        $this->info("Database migrations completed successfully.");

        // Step 9: Run Recommended Settings Seeder
        $this->info("Seeding recommended settings...");
        Artisan::call('db:seed', ['--class' => 'RecommendedSettingsSeeder']);
        $this->info("Recommended settings seeded successfully.");

        // Step 10: Install & Build Frontend (NPM)
        $this->installAndBuildNpm();

        // Step 11: Set Correct Permissions
        $this->updatePermissions();

        // Step 12: Restart FreeSWITCH
        $this->restartFreeSwitch();

        // Step 13: Display Installation Summary
        $this->displayCompletionMessage($username, $password);

        return 0;
    }

    private function runUpgradeSchema()
    {
        $this->info("Running upgrade schema script...");
        shell_exec("cd /var/www/fspbx && php /var/www/fspbx/public/core/upgrade/upgrade_schema.php > /dev/null 2>&1");
        $this->info("Upgrade schema executed successfully.");
    }

    private function runUpgradeDomains()
    {
        $this->info("Running upgrade domains script...");
        shell_exec("cd /var/www/fspbx/public && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade_domains.php > /dev/null 2>&1");
        $this->info("Upgrade domains executed successfully.");
    }

    private function runUpgradeDefaults()
    {
        $this->info("Running upgrade defaults script...");
        shell_exec("cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1");
        $this->info("Upgrade defaults executed successfully.");
    }


    private function installAndBuildNpm()
    {
        $this->info("Installing NPM dependencies...");
        $installProcess = new Process(['npm', 'install'], base_path());
        $installProcess->setTimeout(300);
        $installProcess->run(function ($type, $buffer) {
            echo $buffer; // Show npm install output in real-time
        });
    
        if (!$installProcess->isSuccessful()) {
            throw new ProcessFailedException($installProcess);
        }
    
        $this->info("✅ NPM dependencies installed successfully.\n");
    
        // Start the spinner for progress indication
        $this->info("🚀 Building frontend assets... (This may take a while)");
    
        $spinnerChars = ['-', '\\', '|', '/']; // Spinner animation characters
        $index = 0;
    
        $buildProcess = new Process(['npm', 'run', 'build'], base_path());
        $buildProcess->setTimeout(300);
        $buildProcess->start();
    
        while ($buildProcess->isRunning()) {
            echo "\r\e[36m" . $spinnerChars[$index % 4] . " Building frontend assets... \e[0m";
            usleep(250000); // Update every 250ms
            $index++;
        }
    
        if (!$buildProcess->isSuccessful()) {
            throw new ProcessFailedException($buildProcess);
        }
    
        echo "\r✅ Frontend assets built successfully!          \n";
    }
    
    

    private function updatePermissions()
    {
        $directory = base_path();
        $this->info("Updating ownership for $directory...");
        shell_exec("chown -R www-data:www-data $directory");
        $this->info("Permissions updated successfully.");
    }

    private function restartFreeSwitch()
    {
        $this->info("Restarting FreeSWITCH...");
        $process = new Process(['/bin/systemctl', 'restart', 'freeswitch']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->info("FreeSWITCH restarted successfully.");
    }

    private function displayCompletionMessage($username, $password)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("\e[32m✅ FS PBX Installation Completed Successfully! \e[0m");
        $this->info(str_repeat('=', 60) . "\n");

        // FS PBX ASCII Logo
        $this->line("\e[36m");
        $this->line(" ███████████  █████████     ███████████  ███████████  █████ █████ ");
        $this->line("░░███░░░░░░█ ███░░░░░███   ░░███░░░░░███░░███░░░░░███░░███ ░░███  ");
        $this->line(" ░███   █ ░ ░███    ░░░     ░███    ░███ ░███    ░███ ░░███ ███   ");
        $this->line(" ░███████   ░░█████████     ░██████████  ░██████████   ░░█████    ");
        $this->line(" ░███░░░█    ░░░░░░░░███    ░███░░░░░░   ░███░░░░░███   ███░███   ");
        $this->line(" ░███  ░     ███    ░███    ░███         ░███    ░███  ███ ░░███  ");
        $this->line(" █████      ░░█████████     █████        ███████████  █████ █████ ");
        $this->line("░░░░░        ░░░░░░░░░     ░░░░░        ░░░░░░░░░░░  ░░░░░ ░░░░░  ");
        $this->line("\e[0m"); // Reset color

        $this->info("\n\e[32m🎉 Welcome to FS PBX! 🎉\e[0m");
        $this->info("\n" . str_repeat('=', 60));

        $this->info("\e[33m🔗 Login URL:\e[0m  " . config('app.url'));
        $this->info("\e[33m👤 Username:\e[0m    $username");
        $this->info("\e[33m🔑 Password:\e[0m    $password");

        $this->info("\n" . str_repeat('=', 60));
        $this->info("\e[32m🎉 All installation tasks completed successfully! \e[0m");
        $this->info(str_repeat('=', 60) . "\n");
    }
}
