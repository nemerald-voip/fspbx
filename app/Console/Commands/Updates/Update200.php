<?php

namespace App\Console\Commands\Updates;

use App\Models\FusionCache;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Services\DialplanService;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class Update200
{
    public const APP_UUID = '82965d63-a03e-4e93-962c-364b68da080a';
    private const DIALPLAN_UUID = '63c6af95-d042-442f-87ea-e31e83480666';
    public const TEMPLATE_PATH = 'app/dialplans/resources/switch/conf/dialplan/009_agent_call_track.xml';

    public function getSupervisorProgramsToRestart(): array
    {
        // Load callback request and call-leg status changes in the long-running importer.
        return ['fs-cdr-service'];
    }

    public function apply(): bool
    {
        try {
            \App\Models\MenuItem::query()
                ->where('menu_item_link', '/app/streams/streams.php')
                ->update(['menu_item_link' => '/streams']);
            $this->ensureTemplate();
            $this->installDialplan();
            if (! FusionCache::clearPattern('directory:*')) {
                throw new \RuntimeException('Directory cache invalidation failed.');
            }
            $response = app(FreeswitchEslService::class)->executeCommand('reloadxml');
            if (! is_string($response) || ! str_starts_with($response, '+OK')) {
                throw new \RuntimeException('FreeSWITCH reloadxml failed.');
            }
            return true;
        } catch (Throwable $exception) {
            echo 'Agent call tracking update failed: '.$exception->getMessage()."\n";
            return false;
        }
    }

    protected function ensureTemplate(): void
    {
        $target = public_path(self::TEMPLATE_PATH);
        // Fresh installs ship this file in the separate public repository.
        // Existing servers fetch it like the other dialplan template updates.
        $contents = File::exists($target) ? File::get($target) : Http::connectTimeout(10)
            ->timeout(30)
            ->get('https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/'.self::TEMPLATE_PATH)
            ->throw()
            ->body();

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false || $xml->getName() !== 'extension'
                || (string) $xml['app_uuid'] !== self::APP_UUID) {
                throw new \RuntimeException('Invalid agent call tracking dialplan template.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! File::exists($target)) {
            File::ensureDirectoryExists(dirname($target));
            File::replace($target, $contents);
            echo "Installed the agent call tracking dialplan template.\n";
        }
    }

    private function installDialplan(): void
    {
        DB::transaction(function () {
            $dialplan = Dialplans::where('app_uuid', self::APP_UUID)->whereNull('domain_uuid')->first() ?? new Dialplans();
            $dialplan->forceFill([
                'dialplan_uuid' => $dialplan->dialplan_uuid ?: self::DIALPLAN_UUID,
                'domain_uuid' => null,
                'app_uuid' => self::APP_UUID,
                'dialplan_name' => 'agent-call-track',
                'dialplan_context' => 'global',
                'dialplan_continue' => 'true',
                'dialplan_order' => 9,
                'dialplan_enabled' => true,
                'dialplan_description' => 'Track authenticated agents making calls outside queues.',
            ]);
            $details = [
                '3952c950-72a7-45c6-ade9-072520af17f9' => ['condition', '${fspbx_cc_agent_uuid}', '^[0-9a-fA-F-]{36}$'],
                'ca85f726-2125-48bb-bc52-c5a5b7f927b0' => ['action', 'lua', 'agent_call_track.lua caller'],
            ];
            $rows = [];
            foreach ($details as $uuid => [$tag, $type, $data]) {
                $rows[] = [
                    'dialplan_detail_uuid' => $uuid,
                    'dialplan_uuid' => $dialplan->dialplan_uuid,
                    'domain_uuid' => null,
                    'dialplan_detail_tag' => $tag,
                    'dialplan_detail_type' => $type,
                    'dialplan_detail_data' => $data,
                    'dialplan_detail_group' => 0,
                    'dialplan_detail_order' => count($rows) * 10 + 10,
                    'dialplan_detail_enabled' => 'true',
                    'dialplan_detail_inline' => null,
                    'dialplan_detail_break' => null,
                ];
            }
            $dialplan->dialplan_xml = app(DialplanService::class)->buildXml($dialplan, $rows);
            $dialplan->save();
            $dialplan->dialplan_details()->whereNotIn('dialplan_detail_uuid', array_keys($details))->delete();
            foreach ($rows as $row) {
                DialplanDetails::updateOrCreate(['dialplan_detail_uuid' => $row['dialplan_detail_uuid']], $row);
            }
            DB::afterCommit(fn () => app(DialplanService::class)->clearDialplanCache('global'));
        });
    }
}
