<?php

namespace App\Console\Commands;

use App\Models\Extensions;
use App\Models\SmsDestinations;
use App\Services\Messaging\MessageParticipantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShareMessageNumber extends Command
{
    protected $signature = 'messaging:share-number {domain_uuid} {number} {extensions*}';
    protected $description = 'Set the extensions sharing an SMS number, including its primary extension';

    public function handle(MessageParticipantService $participants): int
    {
        $domain = $this->argument('domain_uuid');
        $number = $participants->normalize($domain, $this->argument('number'));
        $routes = SmsDestinations::where('domain_uuid', $domain)->where('enabled', 'true')->get()
            ->filter(fn ($r) => $participants->normalize($domain, $r->destination) === $number);
        $numbers = array_unique($this->argument('extensions'));
        $extensions = Extensions::without('advSettings')->where('domain_uuid', $domain)->whereIn('extension', $numbers)->get();
        if ($routes->count() !== 1 || $extensions->count() !== count($numbers)
            || !in_array((string) $routes->first()?->chatplan_detail_data, $numbers, true)) {
            $this->error('Use one enabled SMS route and valid extensions from this account. Include the primary extension.');
            return self::FAILURE;
        }
        $route = $routes->first();
        DB::transaction(function () use ($domain, $route, $extensions) {
            DB::table('sms_destination_members')->where('domain_uuid', $domain)
                ->where('sms_destination_uuid', $route->sms_destination_uuid)->delete();
            foreach ($extensions as $extension) {
                DB::table('sms_destination_members')->insert([
                    'sms_destination_member_uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'domain_uuid' => $domain, 'sms_destination_uuid' => $route->sms_destination_uuid,
                    'extension_uuid' => $extension->extension_uuid,
                ]);
            }
        });
        $this->info('Shared number members: '.$extensions->pluck('extension')->implode(', '));
        return self::SUCCESS;
    }
}
