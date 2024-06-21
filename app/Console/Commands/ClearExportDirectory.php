<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ClearExportDirectory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:clear-export-directory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the export directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = Storage::disk('export')->path('');

        if (File::exists($directory)) {
            File::cleanDirectory($directory);
            $this->info('Export directory cleared!');
        } else {
            $this->error('Export directory does not exist!');
        }
    }
}
