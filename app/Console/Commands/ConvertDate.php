<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class ConvertDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example usage:
     *   php artisan date:convert "2025-04-04 19:00:00" America/Chicago UTC
     *
     * @var string
     */
    protected $signature = 'date:convert {date} {from} {to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert a date from one timezone to another';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dateInput = $this->argument('date');   // e.g., "2025-04-04 19:00:00"
        $fromTimezone = $this->argument('from');  // e.g., America/Chicago
        $toTimezone = $this->argument('to');      // e.g., UTC
    
        try {
            // Create a Carbon instance in the source timezone
            $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dateInput, $fromTimezone);
    
            // Convert the date to the target timezone
            $convertedDate = $date->setTimezone($toTimezone);
    
            // Output only the converted date (as a plain text string)
            $this->line($convertedDate->toDateTimeString());
        } catch (\Exception $e) {
            $this->error("Error converting date: " . $e->getMessage());
        }
    }
    
}
