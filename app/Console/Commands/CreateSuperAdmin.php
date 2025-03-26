<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserSetting;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\UserGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'create:superadmin';
    protected $description = 'Create or reset a Superadmin user';

    public function handle()
    {
        $this->info('🔹 FS PBX Superadmin Setup');

        // Prompt for email with validation
        $email = $this->ask('Enter the Superadmin email address');
        $validator = Validator::make(['email' => $email], ['email' => 'required|email']);

        if ($validator->fails()) {
            $this->error('❌ Invalid email format. Please enter a valid email.');
            return 1;
        }

        // Ensure the `admin.localhost` domain exists
        $domain = Domain::firstOrCreate(
            ['domain_name' => 'admin.localhost'],
            ['domain_description' => 'Admin Domain', 'domain_enabled' => true]
        );

        // Generate a secure random password
        $password = Str::random(25);

        // Check if user exists
        $user = User::where('user_email', $email)->first();
        if ($user) {
            // Reset password
            $user->update(
                [
                    'password' => Hash::make($password),
                    'user_enabled' => 'true'
                    ]
            );
            $this->info("🔄 Superadmin '$email' found. Password has been reset.");
        } else {
            // Create new user
            $user = User::create([
                'domain_uuid'  => $domain->domain_uuid,
                'username'     => explode('@', $email)[0], // Use the email prefix as username
                'user_email'   => $email,
                'password'     => Hash::make($password),
                'user_enabled' => 'true',
            ]);
            $this->info("✅ Superadmin '$email' created successfully.");
        }

        // Ensure the user is assigned to the 'superadmin' group
        $group = Groups::where('group_name', 'superadmin')->first();
        if (!$group) {
            $this->error("❌ Superadmin group not found. Ensure database is seeded properly.");
            return 1;
        }

        if (!UserGroup::where('user_uuid', $user->user_uuid)->where('group_name', 'superadmin')->exists()) {
            UserGroup::create([
                'user_group_uuid' => Str::uuid(),
                'domain_uuid'     => $domain->domain_uuid,
                'group_name'      => 'superadmin',
                'group_uuid'      => $group->group_uuid,
                'user_uuid'       => $user->user_uuid,
            ]);
            $this->info("✅ User assigned to the Superadmin group.");
        } else {
            $this->info("🔹 User is already in the Superadmin group.");
        }

        // Ensure default user settings are present
        $this->createUserSettings($user, $domain->domain_uuid);

        // Display credentials securely
        $this->info("\n=========================");
        $this->info("\e[32mSuperadmin Created Successfully! \e[0m");
        $this->info("=========================");
        $this->info("🔗 Login URL: " . config('app.url'));
        $this->info("👤 Email:    $email");
        $this->info("🔑 Password: $password");
        $this->info("\n(Use this password to log in, then change it immediately.)");
        $this->info("=========================");

        return 0;
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
}
