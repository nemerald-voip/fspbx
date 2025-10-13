<?php

namespace App\Console\Commands;

use App\Models\DefaultSettings;
use App\Models\User;
use App\Models\UserSetting;
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

        $user = User::where('user_email', $username)->first();

        if ($user) {
            // Update existing user's password
            $user->update(
                [
                    'password' => Hash::make($password),
                    'user_enabled' => 'true'
                ]
            );
            $this->info("Superadmin user '$username' already exists. Password updated.");
        } else {
            // Create new Superadmin user
            $user = User::create([
                'domain_uuid' => $domain->domain_uuid,
                'username' => 'admin',
                'user_email' => $username,
                'password' => Hash::make($password),
                'user_enabled' => 'true',
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

        // Ensure default user settings are present
        $this->createUserSettings($user, $domain->domain_uuid);

        // Create symlink if it doesn't exist
        $this->createSymlink('/var/www/fspbx/resources/lua', '/usr/share/freeswitch/scripts/lua');

        // Set proper ownership and permissions
        $this->setOwnershipAndPermissions('/var/www/fspbx/resources/lua');

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
        Artisan::call('db:seed', ['--class' => 'RecommendedSettingsSeeder', '--force' => true]);
        $this->info("Recommended settings seeded successfully.");

        // Step 10: Run Recommended Settings Seeder
        $this->info("Seeding settings...");
        Artisan::call('db:seed', ['--force' => true]);  // Add --force flag
        $this->info("Settings seeded successfully.");

        // Step 11: Create FS PBX menu
        $this->info("Creating FS PBX menu...");
        Artisan::call('menu:create-fspbx');
        $this->info("Created FS PBX menu...");

        // Step 12: Run Provision Template seeder
        $this->info("Seeding provisioning templates...");
        Artisan::call('prov:templates:seed'); 
        $this->info("Provisioning templates seeded successfully.");

        // Step 13: Install & Build Frontend (NPM)
        $this->installAndBuildNpm();

        // Step 14: Set Correct Permissions
        $this->updatePermissions();

        // Step 15: Migrate SQLite to RAM
        $this->info("Migrating SQLite to RAM...");
        $this->call('fs:migrate-sqlite-to-ram');
        $this->info("SQLite migration to RAM completed.");

        // Step 16: Set App version
        Artisan::call('version:set', ['version' => config('version.release'), '--force' => true]);
        Artisan::call('config:cache');
        $this->info("App version is " . config('version.release') . ".");

        // $this->info("Updating edit app folder from GitHub...");
        // $process = new \Symfony\Component\Process\Process(['/bin/bash', '/var/www/fspbx/install/fix-edit-app.sh']);
        // $process->setTimeout(180);
        // $process->run();

        // if ($process->isSuccessful()) {
        //     $this->info("✅ Edit app folder updated from GitHub.");
        // } else {
        //     $this->error("❌ Failed to update edit app folder: " . $process->getErrorOutput());
        // }

        // Step 17: Restart FreeSWITCH
        $this->restartFreeSwitch();

        DefaultSettings::where('default_setting_category', 'switch')->delete();
        $this->runUpgradeDefaults();
        $this->runUpgradeDomains();

        // Step 16: Display Installation Summary
        $this->displayCompletionMessage($username, $password);

        return 0;
    }

    private function runUpgradeSchema()
    {
        $this->info("Running upgrade schema script...");
        shell_exec("cd /var/www/fspbx && php /var/www/fspbx/public/core/upgrade/upgrade_schema.php > /dev/null 2>&1");
        $this->info("Upgrade schema executed successfully.");
    }

    private function runUpgradeDefaults()
    {
        $this->info("Running upgrade defaults script...");
        shell_exec("cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1");
        $this->info("Upgrade defaults executed successfully.");
    }

    private function runUpgradeDomains()
    {
        $this->info("Running upgrade domains script...");
        shell_exec("cd /var/www/fspbx/public && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade_domains.php > /dev/null 2>&1");
        $this->info("Upgrade domains executed successfully.");
    }

    private function installAndBuildNpm()
    {
        $this->info("Installing NPM dependencies...");
        $installProcess = new Process(['npm', 'install'], base_path());
        $installProcess->setTimeout(300);
        $installProcess->run();

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
        // Set timeout to 300 seconds (5 minutes)
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->info("FreeSWITCH restarted successfully.");
    }

    /**
     * Ensure the user has the required settings.
     */
    private function createUserSettings(User $user, string $domainUuid)
    {
        $defaultSettings = [
            [
                'user_setting_uuid'  => Str::uuid(),
                'domain_uuid'        => $domainUuid,
                'user_uuid'          => $user->user_uuid,
                'user_setting_category'    => 'domain',
                'user_setting_subcategory' => 'language',
                'user_setting_name'        => 'code',
                'user_setting_value'       => 'en-us',
                'user_setting_enabled'     => true,
            ],
            [
                'user_setting_uuid'  => Str::uuid(),
                'domain_uuid'        => $domainUuid,
                'user_uuid'          => $user->user_uuid,
                'user_setting_category'    => 'domain',
                'user_setting_subcategory' => 'time_zone',
                'user_setting_name'        => 'name',
                'user_setting_value'       => 'America/Los_Angeles',
                'user_setting_enabled'     => true,
            ],
        ];

        foreach ($defaultSettings as $setting) {
            UserSetting::firstOrCreate(
                [
                    'user_uuid'           => $user->user_uuid,
                    'user_setting_category' => $setting['user_setting_category'],
                    'user_setting_subcategory' => $setting['user_setting_subcategory'],
                ],
                $setting
            );
        }

        $this->info("✅ User settings initialized (Language: en-us, Time Zone: America/Los_Angeles).");
    }

    /**
     * Create a symlink if it does not exist.
     *
     * @param string $target The target directory.
     * @param string $link   The link to be created.
     */
    protected function createSymlink(string $target, string $link)
    {
        if (!file_exists($link)) {
            $process = new Process(['ln', '-s', $target, $link]);
            $process->run();

            if ($process->isSuccessful()) {
                echo "✅ Symlink created: $link -> $target\n";
            } else {
                echo "⚠️ Failed to create symlink: $link -> $target\n";
            }
        } else {
            echo "ℹ️ Symlink already exists: $link\n";
        }
        // Fix the symlink's ownership to www-data:www-data
        $this->fixSymlinkOwnership($link);
    }

    /**
     * Fix the ownership of the symlink.
     *
     * @param string $link
     */
    protected function fixSymlinkOwnership(string $link)
    {
        $chownProcess = new Process(['chown', '-h', 'www-data:www-data', $link]);
        $chownProcess->run();

        if ($chownProcess->isSuccessful()) {
            echo "✅ Symlink ownership changed to www-data:www-data for $link\n";
        } else {
            echo "⚠️ Failed to change symlink ownership for $link\n";
        }
    }

    /**
     * Change ownership and permissions of the given path.
     *
     * @param string $path
     */
    protected function setOwnershipAndPermissions(string $path)
    {
        // Change ownership to www-data:www-data
        $chownProcess = new Process(['chown', '-R', 'www-data:www-data', $path]);
        $chownProcess->run();
        if ($chownProcess->isSuccessful()) {
            echo "✅ Ownership set to www-data:www-data for $path\n";
        } else {
            echo "⚠️ Failed to change ownership for $path\n";
        }

        // Change permissions to 755
        $chmodProcess = new Process(['chmod', '-R', '755', $path]);
        $chmodProcess->run();
        if ($chmodProcess->isSuccessful()) {
            echo "✅ Permissions set to 755 for $path\n";
        } else {
            echo "⚠️ Failed to change permissions for $path\n";
        }
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
