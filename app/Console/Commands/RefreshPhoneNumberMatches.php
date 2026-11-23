<?php

namespace App\Console\Commands;

use App\Models\Destinations;
use App\Services\DialplanService;
use App\Services\PhoneNumberMatchRefreshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;
use UnexpectedValueException;

class RefreshPhoneNumberMatches extends Command
{
    protected $signature = 'phone-numbers:refresh-matches {--apply : Update stored expressions and clear affected dialplan caches}';
    protected $description = 'Refresh generated inbound number matches while preserving custom dialplan rules';

    public function handle(PhoneNumberMatchRefreshService $service, DialplanService $dialplans): int
    {
        if (! Schema::hasTable('v_destinations') || ! Schema::hasTable('v_dialplans') || ! Schema::hasTable('v_dialplan_details')) {
            $this->info('Phone Number tables are not installed; no matches to refresh.');
            return self::SUCCESS;
        }
        $apply = (bool) $this->option('apply');
        $counts = ['updated' => 0, 'pending' => 0, 'current' => 0, 'skipped' => 0, 'failed' => 0];
        $contexts = [];
        Destinations::query()->select('destination_uuid')->chunkById(100, function ($numbers) use ($service, $apply, &$counts, &$contexts) {
            foreach ($numbers as $number) {
                try {
                    $result = $service->refresh($number->destination_uuid, $apply);
                    $counts[$result['status']]++;
                    if ($result['context']) $contexts[$result['context']] = true;
                } catch (UnexpectedValueException $exception) {
                    $counts['skipped']++;
                    $this->warn($number->destination_uuid.': '.$exception->getMessage());
                } catch (Throwable $exception) {
                    $counts['failed']++;
                    $this->warn($number->destination_uuid.': '.$exception->getMessage());
                }
            }
        }, 'destination_uuid');
        if ($apply) {
            foreach (array_keys($contexts) as $context) {
                $dialplans->clearDialplanCache($context);
            }
        }
        $this->info(implode(', ', array_map(fn ($key, $count) => $key.': '.$count, array_keys($counts), $counts)));
        if (! $apply) $this->info('Preview only. Run with --apply to refresh these matches.');

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
