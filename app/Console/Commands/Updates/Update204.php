<?php

namespace App\Console\Commands\Updates;

use App\Models\Destinations;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Services\DialplanBuilderService;
use App\Services\DialplanService;
use App\Services\FreeswitchEslService;
use App\Services\OutboundRouteService;
use App\Services\PhoneNumberService;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class Update204
{
    private array $contexts = [];
    private array $counts = ['updated' => 0, 'current' => 0, 'skipped' => 0, 'failed' => 0];

    public function apply(): bool
    {
        $this->contexts = [];
        $this->counts = ['updated' => 0, 'current' => 0, 'skipped' => 0, 'failed' => 0];

        try {
            $scriptDirectory = app(FreeswitchEslService::class)->executeCommand('global_getvar script_dir');
            $scriptDirectory = is_string($scriptDirectory) ? trim($scriptDirectory) : null;
            if (! is_string($scriptDirectory) || ! str_starts_with($scriptDirectory, '/')) {
                throw new RuntimeException('Could not resolve the FreeSWITCH runtime script directory.');
            }
            foreach (['diversion.lua', 'resources/functions/diversion.lua'] as $script) {
                $source = resource_path('freeswitch_scripts/' . $script);
                $runtime = rtrim($scriptDirectory, '/') . '/' . $script;
                if (! is_readable($source) || ! is_readable($runtime) || hash_file('sha256', $source) !== hash_file('sha256', $runtime)) {
                    throw new RuntimeException("Missing FreeSWITCH helper: {$script}");
                }
            }

            $numbers = app(PhoneNumberService::class);
            // No enabled/account filters: disabled Phone Numbers also need the
            // capture when subsequently enabled. Keep the repair in this update.
            Destinations::query()->chunkById(100, function ($phoneNumbers) use ($numbers) {
                foreach ($phoneNumbers as $number) {
                    $this->runForRoute('Phone Number ' . $number->destination_uuid, function () use ($number, $numbers) {
                        $did = $numbers->originalDid($number);
                        if ($did === null) {
                            throw new UnexpectedValueException('No single valid configured DID; route preserved.');
                        }
                        $plan = Dialplans::query()->whereKey($number->dialplan_uuid)->lockForUpdate()->first();
                        if (! $plan || $plan->domain_uuid !== $number->domain_uuid) {
                            throw new UnexpectedValueException('Missing dialplan or different account; route preserved.');
                        }
                        $domain = Domain::query()->whereKey($number->domain_uuid)->value('domain_name');
                        if (! $domain) {
                            throw new UnexpectedValueException('Missing account; route preserved.');
                        }
                        return $this->patchInbound($plan, $number, $did, $domain);
                    });
                }
                echo "Phone Numbers processed: " . array_sum($this->counts) . ".\n";
            }, 'destination_uuid');

            Dialplans::query()->where('app_uuid', '8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3')
                ->chunkById(100, function ($plans) {
                    foreach ($plans as $plan) {
                        $this->runForRoute('Outbound route ' . $plan->dialplan_uuid, function () use ($plan) {
                            $locked = Dialplans::query()->whereKey($plan->getKey())->lockForUpdate()->first();
                            if (! $locked) {
                                throw new UnexpectedValueException('Dialplan was removed during the update; skipped.');
                            }
                            return $this->patchOutbound($locked);
                        });
                    }
                    echo "Routes processed: " . array_sum($this->counts) . ".\n";
                }, 'dialplan_uuid');
            echo "Outbound Diversion actions were added disabled; stored outbound XML was preserved. Enable the actions and save the route to activate them.\n";

            foreach (array_keys($this->contexts) as $context) {
                app(DialplanService::class)->clearDialplanCache($context);
            }
            // Reload on retries as well: a previous run may have committed all
            // rows but failed to invalidate a cache or contact FreeSWITCH.
            $response = app(FreeswitchEslService::class)->executeCommand('reloadxml');
            if (! is_string($response) || ! preg_match('/^\+?OK\b/i', trim($response))) {
                throw new RuntimeException('FreeSWITCH XML reload failed: ' . (is_scalar($response) ? $response : 'no valid response'));
            }
            echo "FreeSWITCH XML reload succeeded.\n";
        } catch (Throwable $exception) {
            echo "Update 2.0.4 failed: {$exception->getMessage()}\n";
            $this->counts['failed']++;
        }

        echo "Diversion update: {$this->counts['updated']} updated, {$this->counts['current']} already current, "
            . "{$this->counts['skipped']} skipped, {$this->counts['failed']} failed.\n";

        return $this->counts['failed'] === 0;
    }

    private function runForRoute(string $label, callable $callback): void
    {
        try {
            $changed = DB::transaction($callback);
            $this->counts[$changed ? 'updated' : 'current']++;
        } catch (UnexpectedValueException $exception) {
            $this->counts['skipped']++;
            echo "{$label}: {$exception->getMessage()}\n";
        } catch (Throwable $exception) {
            $this->counts['failed']++;
            echo "{$label}: FAILED: {$exception->getMessage()}\n";
        }
    }

    private function document(string $xml): DOMDocument
    {
        if (trim($xml) === '') {
            throw new UnexpectedValueException('Missing stored dialplan XML; route preserved.');
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET) || $document->doctype
                || $document->documentElement?->tagName !== 'extension') {
                throw new UnexpectedValueException('Unsupported dialplan XML; route preserved.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return $document;
    }

    private function details(Dialplans $plan): Collection
    {
        return $plan->dialplan_details()
            ->orderBy('dialplan_detail_group')->orderBy('dialplan_detail_order')->get();
    }

    private function detailEnabled(DialplanDetails $row): bool
    {
        // Preserve the existing handling of legacy NULL values; otherwise use
        // the model's enabled accessor for PostgreSQL booleans and text values.
        return $row->getRawOriginal('dialplan_detail_enabled') === null || $row->dialplan_detail_enabled;
    }

    private function patchInbound(Dialplans $plan, Destinations $number, string $did, string $domain): bool
    {
        $document = $this->document((string) $plan->dialplan_xml);
        $xpath = new DOMXPath($document);
        $details = $this->details($plan);
        $groups = $details->groupBy('dialplan_detail_group')->filter(fn ($rows) => $rows->contains(
            fn ($row) => in_array($row->dialplan_detail_tag, ['condition', 'regex'], true)
                && $row->dialplan_detail_data === $number->destination_number_regex
        ))->values();
        $conditions = [];
        foreach ($xpath->query('/extension/condition') as $condition) {
            $matches = $condition->getAttribute('expression') === $number->destination_number_regex;
            foreach ($xpath->query('./regex', $condition) as $regex) {
                $matches = $matches || $regex->getAttribute('expression') === $number->destination_number_regex;
            }
            if ($matches) {
                $conditions[] = $condition;
            }
        }
        if (! $conditions || count($conditions) !== $groups->count()) {
            throw new UnexpectedValueException('Custom/missing number conditions or editable details; route preserved.');
        }

        $data = DialplanBuilderService::originalDidExport($did);
        $changed = false;
        foreach ($conditions as $index => $condition) {
            $account = null;
            $host = null;
            $lastSetup = null;
            $capture = null;
            $routingStarted = false;
            foreach ($xpath->query('./action', $condition) as $action) {
                $actionData = $action->getAttribute('data');
                $application = $action->getAttribute('application');
                if ($application === 'set' && $actionData === 'domain_uuid=' . $number->domain_uuid) {
                    $account = $lastSetup = $action;
                }
                if ($application === 'set' && $actionData === 'domain_name=' . $domain) {
                    $host = $lastSetup = $action;
                }
                if ($this->isDidCapture($application, $actionData)) {
                    $expected = $application === 'export' && $actionData === $data;
                    if ($capture || ! $expected || ! $account || ! $host || $routingStarted) {
                        throw new UnexpectedValueException('Conflicting DID capture; route preserved.');
                    }
                    $capture = $action;
                } elseif (! in_array($application, ['set', 'export'], true)) {
                    if (! $account || ! $host) {
                        throw new UnexpectedValueException('Routing precedes account setup; route preserved.');
                    }
                    $routingStarted = true;
                }
            }
            if (! $account || ! $host) {
                throw new UnexpectedValueException('Custom account setup; route preserved.');
            }
            if (! $capture) {
                $capture = $document->createElement('action');
                $capture->setAttribute('application', 'export');
                $capture->setAttribute('data', $data);
                $condition->insertBefore($capture, $lastSetup->nextSibling);
                $changed = true;
            }

            $rows = $groups[$index];
            $existing = $rows->filter(fn ($row) => $row->dialplan_detail_tag === 'action'
                && $this->isDidCapture((string) $row->dialplan_detail_type, (string) $row->dialplan_detail_data));
            if ($existing->count() > 1 || ($existing->isNotEmpty()
                && ($existing->first()->dialplan_detail_type !== 'export'
                    || $existing->first()->dialplan_detail_data !== $data
                    || ! $this->detailEnabled($existing->first())))) {
                throw new UnexpectedValueException('Conflicting editable DID capture; route preserved.');
            }
            if ($existing->isEmpty()) {
                $this->insertCaptureDetails($plan, $rows, $data, $domain);
                $changed = true;
            }
        }
        $this->contexts[(string) $plan->dialplan_context] = true;
        if ($changed) {
            $plan->dialplan_xml = $document->saveXML($document->documentElement);
            $plan->saveOrFail();
        }
        return $changed;
    }

    private function isDidCapture(string $application, string $data): bool
    {
        return in_array($application, ['set', 'export'], true) && str_starts_with($data, 'original_did=');
    }

    private function insertCaptureDetails(Dialplans $plan, Collection $rows, string $data, string $domain): void
    {
        $setup = ['call_direction=inbound', 'domain_uuid=' . $plan->domain_uuid, 'domain_name=' . $domain];
        $additions = [];
        foreach ($setup as $value) {
            if (! $rows->contains(fn ($row) => in_array($row->dialplan_detail_type, ['set', 'export'], true)
                && $row->dialplan_detail_data === $value)) {
                $additions[] = [str_starts_with($value, 'call_direction=') ? 'export' : 'set', $value, 'true'];
            }
        }
        $additions[] = ['export', $data, 'false'];
        $before = $rows->first(fn ($row) => $row->dialplan_detail_tag === 'action'
            && ! in_array($row->dialplan_detail_data, $setup, true));
        if (! $before) {
            throw new UnexpectedValueException('No recognized routing actions in editable details; route preserved.');
        }
        if ($rows->contains(fn ($row) => in_array($row->dialplan_detail_data, $setup, true)
            && (! $this->detailEnabled($row) || $row->dialplan_detail_order >= $before->dialplan_detail_order))) {
            throw new UnexpectedValueException('Custom account setup in editable details; route preserved.');
        }
        $previous = $rows->filter(fn ($row) => $row->dialplan_detail_order < $before->dialplan_detail_order)
            ->max('dialplan_detail_order') ?? 0;
        $order = (int) $previous + 1;
        $shift = max(0, $order + count($additions) - (int) $before->dialplan_detail_order);
        if ($shift > 0) {
            $plan->dialplan_details()
                ->where('dialplan_detail_group', $before->dialplan_detail_group)
                ->where('dialplan_detail_order', '>=', $before->dialplan_detail_order)
                ->increment('dialplan_detail_order', $shift);
        }
        foreach ($additions as [$application, $value, $inline]) {
            $detail = $plan->dialplan_details()->make([
                'domain_uuid' => $plan->domain_uuid,
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => $application,
                'dialplan_detail_data' => $value,
                'dialplan_detail_inline' => $inline,
                'dialplan_detail_enabled' => 'true',
                'dialplan_detail_group' => $before->dialplan_detail_group,
                'dialplan_detail_order' => $order++,
            ]);
            $detail->saveOrFail();
        }
    }

    private function patchOutbound(Dialplans $plan): bool
    {
        $document = $this->document((string) $plan->dialplan_xml);
        $details = $this->details($plan);
        $xpath = new DOMXPath($document);
        // Preserve existing choices, including a previously enabled helper or
        // disabled rows from an earlier run. Never rewrite outbound XML here.
        if ($details->contains(fn ($row) => $row->dialplan_detail_type === 'lua'
            && in_array($row->dialplan_detail_data, ['diversion.lua', 'diversion.lua clear'], true))
            || $xpath->query('//*[@application="lua" and (@data="diversion.lua" or @data="diversion.lua clear")]')->length > 0) {
            return false;
        }
        $changed = false;
        $used = [];
        $branches = 0;
        foreach ($xpath->query('//condition') as $condition) {
            foreach (['action', 'anti-action'] as $tag) {
                $actions = iterator_to_array($xpath->query('./' . $tag, $condition));
                if (! $actions) continue;
                $branches++;
                $mode = null;
                $started = false;
                $hasRouting = collect($actions)->contains(fn ($node) => $this->isOutboundRouting(
                    $node->getAttribute('application'), $node->getAttribute('data')
                ));
                foreach ($actions as $action) {
                    $application = $action->getAttribute('application');
                    $data = $action->getAttribute('data');
                    $row = $details->first(fn ($row) => ! isset($used[$row->dialplan_detail_uuid])
                        && $this->detailEnabled($row) && $row->dialplan_detail_tag === $tag
                        && $row->dialplan_detail_type === $application && $row->dialplan_detail_data === $data);
                    if (! $row) {
                        throw new UnexpectedValueException('Outbound XML differs from editable details; route preserved.');
                    }
                    $used[$row->dialplan_detail_uuid] = true;
                    $started = $started || ! $hasRouting || $this->isOutboundRouting($application, $data);
                    if (! $started) {
                        continue;
                    }
                    $next = OutboundRouteService::diversionAction($application, $data);
                    if ($mode !== $next) {
                        $this->insertOutboundAction($plan, $row, $next);
                        $changed = true;
                    }
                    // A saved Bridge can set its own header after resolving its
                    // destination. Reevaluate before the following action.
                    $mode = $application === 'lua' && str_starts_with($data, 'bridge.lua ') ? null : $next;
                }
            }
        }
        if (! $branches) throw new UnexpectedValueException('No supported outbound action branches; route preserved.');
        return $changed;
    }

    private function isOutboundRouting(string $application, string $data): bool
    {
        return in_array($application, ['bridge', 'transfer', 'execute_extension'], true)
            || ($application === 'lua' && str_starts_with($data, 'bridge.lua '));
    }

    private function insertOutboundAction(Dialplans $plan, DialplanDetails $before, string $data): void
    {
        // Read fresh ordering because an earlier insertion may have shifted it.
        $rows = $plan->dialplan_details()
            ->where('dialplan_detail_group', $before->dialplan_detail_group);
        $order = (int) (clone $rows)->where('dialplan_detail_uuid', $before->dialplan_detail_uuid)->value('dialplan_detail_order');
        $previous = (clone $rows)->where('dialplan_detail_order', '<', $order)->max('dialplan_detail_order');
        if ($previous !== null && (int) $previous >= $order - 1) {
            (clone $rows)->where('dialplan_detail_order', '>=', $order)->increment('dialplan_detail_order');
        } else {
            $order--;
        }
        $detail = $plan->dialplan_details()->make([
            'domain_uuid' => $plan->domain_uuid, 'dialplan_detail_tag' => $before->dialplan_detail_tag,
            'dialplan_detail_type' => 'lua', 'dialplan_detail_data' => $data,
            'dialplan_detail_inline' => 'false', 'dialplan_detail_enabled' => 'false',
            'dialplan_detail_group' => $before->dialplan_detail_group, 'dialplan_detail_order' => $order,
        ]);
        $detail->saveOrFail();
    }
}
