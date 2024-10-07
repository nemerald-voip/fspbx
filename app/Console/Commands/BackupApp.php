<?php

namespace App\Console\Commands;

use App\Models\DefaultSettings;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a backup of the application and database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backup...');

        $now = date('Y-m-d');

        // Retrieve backup path from the DefaultSettings model
        $backupSetting = DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 'backup_path')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        // Set default backup path if the setting is not found
        $mainBackupDir = $backupSetting ?: '/var/backups/fspbx';

        $backupDir = "$mainBackupDir/postgresql";
        
        $db = config('database.connections')[config('database.default')];

        $dbHost = $db['host'];
        $dbPort = $db['port'];
        $dbUser = $db['username'];
        $dbName = $db['database'];
        $dbPassword = $db['password'];
        $dbSchema = $db['schema'] ?? 'public';

        // Create backup directories if they don't exist
        $this->executeCommand("mkdir -p $backupDir");

        // Delete old PostgreSQL backups older than 7 days if the directory exists
        if (is_dir($backupDir)) {
            $this->info('Cleaning up old PostgreSQL backups...');
            // Check if any files matching the pattern exist
            $files = glob("$backupDir/fusionpbx_pgsql*");
            if (count($files) > 0) {
                $this->executeCommand("find $backupDir/fusionpbx_pgsql* -mtime +7 -exec rm -f {} \\;");
            } else {
                $this->info("No old PostgreSQL backups found to delete.");
            }
        } else {
            $this->info("Backup directory $backupDir does not exist. Skipping PostgreSQL backup cleanup.");
        }

        // Delete old main backups older than 2 days
        if (is_dir($mainBackupDir)) {
            $this->info('Cleaning up old main backups...');
            // Check if any `.tgz` files exist in the directory
            $tgzFiles = glob("$mainBackupDir/*.tgz");
            if (count($tgzFiles) > 0) {
                $this->executeCommand("find $mainBackupDir/*.tgz -mtime +2 -exec rm -f {} \\;");
            } else {
                $this->info("No old main backups found to delete.");
            }
        } else {
            $this->info("Main backup directory $mainBackupDir does not exist. Skipping main backup cleanup.");
        }


        // Backup the PostgreSQL database
        $backupFile = "$backupDir/fusionpbx_pgsql_$now.sql";
        $this->info("Backing up PostgreSQL database to $backupFile...");
        $this->executeCommand("export PGPASSWORD=$dbPassword && pg_dump --verbose -Fc --host=$dbHost --port=$dbPort -U $dbUser $dbName --schema=$dbSchema -f $backupFile");

        // Paths to be backed up
        $directories = [
            '/var/www/freeswitchpbx',
            '/var/www/fspbx',
            '/usr/share/freeswitch',
            '/etc/freeswitch',
            '/usr/share/fusionpbx/templates/provision',
            '/usr/share/fspbx/templates/provision',
        ];

        // Check which directories exist
        $existingDirs = array_filter($directories, function ($dir) {
            return is_dir($dir);
        });

        // Package the backup
        $mainBackupFile = "$mainBackupDir/backup_$now.tgz";

        // Generate the tar command only for existing directories
        $tarCommand = "tar --exclude='*/.git/*' --exclude='*/music/default/*' --exclude='*/june/*' --exclude='*/callie/*' --exclude='*/mario/*' --exclude='*/node_modules/*' --exclude='/var/www/freeswitchpbx/vendor' -zvcf $mainBackupFile $backupFile " . implode(' ', $existingDirs);

        $this->info("Packaging the backup into $mainBackupFile...");
        $this->executeCommand($tarCommand);

        $this->info('Backup completed successfully!');
    }

    /**
     * Execute a shell command with optional timeout.
     */
    protected function executeCommand($command, $timeout = 300)
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->setTty(true); // Preserve color output
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->output->write($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error("Command '$command' failed.");
            exit(1);
        }
    }
}
