<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\UserGroup;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
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

        // Step 4: Create Superadmin User
        $username = "admin@fspbx.net";

        $user = User::create([
            'domain_uuid' => $domain->domain_uuid,
            'username' => $username,
            'password' => Hash::make(Str::random(25)),
            'user_enabled' => true,
        ]);

        $this->info("Superadmin user '$username' created successfully.");

        // Step 5: Assign User to Superadmin Group
        $group = Groups::where('group_name', 'superadmin')->first();
        if (!$group) {
            $this->error("Superadmin group not found. Please check your database.");
            return 1;
        }

        UserGroup::create([
            'user_group_uuid' => Str::uuid(),
            'domain_uuid' => $domain->domain_uuid,
            'group_name' => 'superadmin',
            'group_uuid' => $group->group_uuid,
            'user_uuid' => $user->user_uuid,
        ]);

        $this->info("User assigned to Superadmin group successfully.");

        // Step 6: Run Upgrade Defaults
        $this->runUpgradeDefaults();

        // Step 7: Restart FreeSWITCH
        $this->restartFreeSwitch();

        $this->info("FS PBX installation completed successfully.");
        $this->info("Login details:");
        $this->info(config('app.url'));
        $this->info("Username: $username");

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
}
