<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        $fileConfigs = [
            '/etc/freeswitch/autoload_configs/switch.conf.xml' => [
                [
                    'original' => '<!--<param name="core-db-name" value="/dev/shm/core.db" />-->',
                    'replace' => '<param name="core-db-name" value="/dev/shm/core.db" />',
                ],
                [
                    'original' => '<!--<param name="auto-create-schemas" value="true"/>-->',
                    'replace' => '<param name="auto-create-schemas" value="true"/>',
                ],
                [
                    'original' => '<!--<param name="auto-create-schemas" value="false"/>-->',
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

        $this->info("SQLite migration to RAM complete.");
        return Command::SUCCESS;
    }
}
