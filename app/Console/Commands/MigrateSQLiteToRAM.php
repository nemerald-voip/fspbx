<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SipProfiles;
use App\Models\SipProfileSettings;
use App\Models\SwitchVariable;
use App\Models\DefaultSettings;
use Illuminate\Support\Str;

class MigrateSQLiteToRAM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fs:migrate-sqlite-to-ram';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate SQLite databases to RAM by modifying FreeSWITCH configuration files.';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->info("Starting SQLite to RAM migration...");

        $this->modifyConfigFiles();
        $this->updateDatabaseRecords();
        $this->insertSwitchVariables();
        $this->updateDefaultSettings();
        $this->updateFusionPBXConfig();
        $this->removeDatabaseFiles();

        $this->info("SQLite to RAM migration complete.");

        $this->info("------------");
        $this->info("TO DO:");
        $this->info("1) Restart FreeSwitch");
        $this->info("2) Flash Cache");
        $this->info("3) Re-scan SIP Profiles");
        
        return Command::SUCCESS;

    }

    /**
     * Modify configuration files.
     */
    private function modifyConfigFiles()
    {
        $fileConfigs = [
            '/etc/freeswitch/autoload_configs/switch.conf.xml' => [
                [
                    'original' => '<!-- <param name="core-db-name" value="/dev/shm/core.db" /> -->',
                    'replace' => '<param name="core-db-name" value="/dev/shm/core.db" />',
                ],
                [
                    'original' => '<!-- <param name="auto-create-schemas" value="true"/> -->',
                    'replace' => '<param name="auto-create-schemas" value="true"/>',
                ],
                [
                    'original' => '<!-- <param name="auto-create-schemas" value="false"/> -->',
                    'replace' => '<param name="auto-create-schemas" value="true"/>',
                ],
                [
                    'original' => '<param name="auto-create-schemas" value="false"/>',
                    'replace' => '<param name="auto-create-schemas" value="true"/>',
                ],
            ],
            '/etc/freeswitch/autoload_configs/fifo.conf.xml' => [
                [
                    'original' => '<!--<param name="odbc-dsn" value="$${dsn}"/>-->',
                    'replace' => '<param name="odbc-dsn" value="sqlite:///dev/shm/fifo.db"/>',
                ],
            ],
            '/etc/freeswitch/autoload_configs/db.conf.xml' => [
                [
                    'original' => '<!--<param name="odbc-dsn" value="$${dsn}"/>-->',
                    'replace' => '<param name="odbc-dsn" value="sqlite:///dev/shm/call_limit.db"/>',
                ],
            ],
        ];

        foreach ($fileConfigs as $file => $replacements) {
            if (!file_exists($file)) {
                $this->error("File not found: $file");
                continue;
            }

            $content = file_get_contents($file);
            $updated = false;

            foreach ($replacements as $replacement) {
                if (strpos($content, $replacement['original']) !== false) {
                    $content = str_replace($replacement['original'], $replacement['replace'], $content);
                    $this->info("Updated line in: $file");
                    $updated = true;
                }
            }

            if ($updated) {
                file_put_contents($file, $content);
            } else {
                $this->info("No changes needed for: $file");
            }
        }
    }

    /**
     * Update database records.
     */
    private function updateDatabaseRecords()
    {
        $this->info("Updating SIP profile settings...");

        // Retrieve all SIP profiles
        $sipProfiles = SipProfiles::with('settings')->get();

        foreach ($sipProfiles as $sipProfile) {
            // Construct the odbc-dsn value
            $odbcDsnValue = "sqlite:///dev/shm/sofia_reg_{$sipProfile->sip_profile_name}.db";

            // Find or create the odbc-dsn setting
            $setting = $sipProfile->settings()->firstOrNew([
                'sip_profile_setting_name' => 'odbc-dsn',
            ]);

            // Update the setting value and enable it
            $setting->sip_profile_setting_value = $odbcDsnValue;
            $setting->sip_profile_setting_enabled = 'true';
            $setting->save();

            $this->info("Updated odbc-dsn for SIP profile: {$sipProfile->sip_profile_name}");
        }
    }

    /**
     * Insert or update switch variables.
     */
    private function insertSwitchVariables()
    {
        $this->info("Inserting Switch Variables...");

        $variables = [
            [
                'var_uuid' => 'eeb9db7f-ee49-4c80-81a3-3b4391a1c7f0',
                'var_category' => 'DSN',
                'var_name' => 'dsn',
                'var_value' => 'sqlite:///dev/shm/core.db',
                'var_command' => 'set',
                'var_hostname' => null,
                'var_enabled' => 'true',
            ],
            [
                'var_uuid' => '7121e1ab-c41e-42ce-b5e6-e6c296767fd4',
                'var_category' => 'DSN',
                'var_name' => 'dsn_callcenter',
                'var_value' => 'sqlite:///dev/shm/callcenter.db',
                'var_command' => 'set',
                'var_hostname' => null,
                'var_enabled' => 'true',
            ],
        ];

        foreach ($variables as $variable) {
            SwitchVariable::updateOrCreate(
                ['var_uuid' => $variable['var_uuid']],
                $variable
            );

            $this->info("Inserted/Updated variable: {$variable['var_name']}");
        }
    }

    /**
     * Update default settings.
     */
    private function updateDefaultSettings()
    {
        $this->info("Updating Default Settings...");

        $updated = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'db')
            ->where('default_setting_name', 'dir')
            ->update(['default_setting_value' => '/dev/shm']);

        if ($updated) {
            $this->info("Updated default setting for switch->db->dir to '/dev/shm'.");
        } else {
            $this->warn("No default setting found for switch->db->dir.");
        }
    }

    /**
     * Update FusionPBX config file.
     */
    private function updateFusionPBXConfig()
    {
        $this->info("Updating FusionPBX config file...");

        $configFile = '/etc/fusionpbx/config.conf';

        if (!file_exists($configFile)) {
            $this->error("Config file not found: $configFile");
            return;
        }

        $content = file_get_contents($configFile);

        // Update the line
        $originalLine = 'database.1.path = /var/lib/freeswitch/db';
        $newLine = 'database.1.path = /dev/shm';

        if (strpos($content, $originalLine) !== false) {
            $content = str_replace($originalLine, $newLine, $content);
            file_put_contents($configFile, $content);
            $this->info("Updated database.1.path in $configFile.");
        } else {
            $this->warn("No changes made. Original line not found in $configFile.");
        }
    }

    /**
     * Remove old database files.
     */
    private function removeDatabaseFiles()
    {
        $this->info("Removing old database files...");

        $directory = '/var/lib/freeswitch/db/';
        $pattern = '*.db*';

        if (!is_dir($directory)) {
            $this->error("Directory not found: $directory");
            return;
        }

        $files = glob($directory . $pattern);

        if (empty($files)) {
            $this->info("No database files to remove in $directory.");
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $this->info("Removed: $file");
            }
        }

        $this->info("Old database files removed.");
    }
}
