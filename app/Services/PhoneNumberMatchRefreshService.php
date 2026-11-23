<?php

namespace App\Services;

use App\Models\Destinations;
use App\Models\Dialplans;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class PhoneNumberMatchRefreshService
{
    private const DID_FIELDS = [
        '${fspbx_inbound_did}', 'destination_number', '${destination_number}',
        'caller_destination', '${caller_destination}', '${sip_to_user}', '${sip_req_user}',
    ];

    public function __construct(private PhoneNumberService $numbers) {}

    /** Replace only recognized generated DID conditions, preserving all actions. */
    public function refresh(string $uuid, bool $apply): array
    {
        return DB::transaction(function () use ($uuid, $apply) {
            $number = Destinations::query()->whereKey($uuid)->lockForUpdate()->firstOrFail();
            $old = (string) $number->destination_number_regex;
            $new = $this->numbers->dialplanMatchForDestination($number)['expression'];
            if ($old === $new) {
                // Retrying after a cache failure must invalidate current rows too.
                return ['status' => 'current', 'context' => Dialplans::query()->whereKey($number->dialplan_uuid)
                    ->where('domain_uuid', $number->domain_uuid)->value('dialplan_context')];
            }
            if (! $this->isGeneratedExpression($number, $old)) {
                throw new UnexpectedValueException('Custom or unrecognized number expression; preserved.');
            }

            $plan = Dialplans::query()->whereKey($number->dialplan_uuid)
                ->where('domain_uuid', $number->domain_uuid)->lockForUpdate()->first();
            if (! $plan) {
                throw new UnexpectedValueException('No dialplan in the same account; preserved.');
            }
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $valid = trim((string) $plan->dialplan_xml) !== ''
                    && $document->loadXML($plan->dialplan_xml, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (! $valid || $document->doctype || $document->documentElement?->tagName !== 'extension') {
                throw new UnexpectedValueException('Missing or unsupported dialplan XML; preserved.');
            }

            // Older separate-area/trunk expressions captured the whole carrier
            // input in $1. Do not silently change custom actions using those groups.
            if (str_starts_with($old, '^(') && (string) $number->destination_prefix !== '') {
                foreach ((new DOMXPath($document))->query('//action | //anti-action') as $action) {
                    if (preg_match('/\$[1-9][0-9]*/', $action->getAttribute('data'))) {
                        throw new UnexpectedValueException('Custom action uses legacy number captures; preserved.');
                    }
                }
            }

            $nodes = [];
            foreach ((new DOMXPath($document))->query('/extension/condition | /extension/condition/regex') as $node) {
                if (! in_array($node->getAttribute('field'), self::DID_FIELDS, true)) continue;
                if ($node->getAttribute('expression') !== $old) {
                    throw new UnexpectedValueException('Custom DID condition in XML; preserved.');
                }
                $nodes[] = $node;
            }
            $details = $plan->dialplan_details()->where('domain_uuid', $number->domain_uuid)
                ->whereIn('dialplan_detail_tag', ['condition', 'regex'])
                ->whereIn('dialplan_detail_type', self::DID_FIELDS)->lockForUpdate()->get();
            if ($nodes === [] || count($nodes) !== $details->count()
                || $details->contains(fn ($row) => $row->dialplan_detail_data !== $old)) {
                throw new UnexpectedValueException('Custom or inconsistent editable DID conditions; preserved.');
            }

            if ($apply) {
                foreach ($nodes as $node) $node->setAttribute('expression', $new);
                DB::table('v_destinations')->where('destination_uuid', $uuid)
                    ->where('domain_uuid', $number->domain_uuid)->update(['destination_number_regex' => $new]);
                DB::table('v_dialplans')->where('dialplan_uuid', $plan->dialplan_uuid)
                    ->where('domain_uuid', $number->domain_uuid)
                    ->update(['dialplan_xml' => $document->saveXML($document->documentElement)]);
                DB::table('v_dialplan_details')->whereIn('dialplan_detail_uuid', $details->modelKeys())
                    ->update(['dialplan_detail_data' => $new]);
            }

            return ['status' => $apply ? 'updated' : 'pending', 'context' => $plan->dialplan_context];
        });
    }

    private function isGeneratedExpression(Destinations $number, string $expression): bool
    {
        // Recognize historical stock expressions only for the one-time repair.
        // Runtime generation belongs exclusively to PhoneNumberService.
        $body = (string) $number->destination_number;
        $area = (string) $number->destination_area_code;
        $trunk = (string) $number->destination_trunk_prefix;
        $prefix = ltrim((string) $number->destination_prefix, '+');
        // The old generator treated the literal zero as absent.
        if ($prefix !== '' && $trunk !== '' && $trunk !== '0') {
            $parts = ['\\+?'.$prefix.'?('.$area.$body.')', $trunk.'?('.$area.$body.')'];
            if ($area !== '') $parts[] = '('.$area.$body.')';
            return $expression === '^('.implode('|', $parts).')$';
        }
        if ($prefix !== '' && $area !== '') {
            return $expression === '^(\\+?'.$prefix.'?('.$area.$body.'))$';
        }
        if (str_starts_with($body, '+')) {
            $body = '\\+?'.substr($body, 1);
        } else {
            $body = '('.$body.')';
        }
        $body = str_ireplace(['N', 'X', 'Z'], ['[2-9]', '[0-9]', '[1-9]'], $body);
        $expected = $prefix === '' ? '^'.$body.'$'
            : '^\\+?'.(strlen($prefix) === 1 ? $prefix.'?' : '(?:'.$prefix.')?').$body.'$';

        return $expression === $expected;
    }
}
