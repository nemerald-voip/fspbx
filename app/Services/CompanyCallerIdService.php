<?php

namespace App\Services;

use App\Models\Dialplans;
use App\Models\Domain;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyCallerIdService
{
    private const APP_UUID = '3b290b41-cec8-467d-8b12-d51cdc74a81e';

    public const FIELDS = [
        'outbound_caller_id_number',
        'emergency_caller_id_number',
        'outbound_caller_id_name',
        'emergency_caller_id_name',
    ];

    public function __construct(private DialplanService $dialplans) {}

    public function options(Domain $domain): array
    {
        $plans = $this->plans($domain);
        $plan = $plans->count() === 1 ? $plans->first() : null;
        $values = $plan ? $this->readValues($plan) : null;
        $custom = $plans->count() > 1 || ($plan && ($values === null || $this->hasCustomDetails($plan)
            || filled($plan->hostname)
            || !in_array($plan->dialplan_context, [$domain->domain_name, '${domain_name}', 'global'], true)));
        $shared = $plan && ($plan->domain_uuid !== $domain->domain_uuid
            || $plan->dialplan_context !== $domain->domain_name);

        return [
            'values' => $values ?? array_fill_keys(self::FIELDS, null),
            'status' => $custom ? 'custom' : ($shared ? 'shared' : (!$plan ? 'missing' : ($plan->dialplan_enabled ? 'ready' : 'disabled'))),
            'enabled' => $plan?->dialplan_enabled ?? false,
            'can_manage' => !$custom && !$shared && userCheckPermission('extension_edit'),
            'revision' => $this->revision($plans),
            'save_route' => route('extensions.company-caller-id.update', ['domain' => $domain->domain_uuid]),
        ];
    }

    public function save(Domain $domain, array $input): void
    {
        DB::transaction(function () use ($domain, $input) {
            // Serialize setup when this account does not have a default dialplan yet.
            $domain = Domain::whereKey($domain->domain_uuid)->lockForUpdate()->firstOrFail();
            $plans = $this->plans($domain, true);
            $plan = $plans->count() === 1 ? $plans->first() : null;

            abort_unless(userCheckPermission('extension_edit'), 403);

            if (!hash_equals($this->revision($plans), $input['revision'])) {
                throw ValidationException::withMessages(['company_caller_id' => __('Company caller ID changed. Reopen the extension and try again.')]);
            }

            if ($plans->count() > 1 || ($plan && ($plan->domain_uuid !== $domain->domain_uuid
                || $plan->dialplan_context !== $domain->domain_name
                || filled($plan->hostname) || $this->readValues($plan) === null || $this->hasCustomDetails($plan)))) {
                throw ValidationException::withMessages(['company_caller_id' => __('Manage these defaults in Dialplan Manager.')]);
            }

            $details = [[
                'dialplan_detail_tag' => 'condition',
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 5,
            ]];
            foreach (self::FIELDS as $index => $field) {
                // A missing name assignment should remain absent unless a name is supplied.
                if (str_ends_with($field, '_name') && blank($input[$field] ?? null)
                    && !str_contains((string) $plan?->dialplan_xml, 'default_' . $field . '=')) {
                    continue;
                }
                $details[] = [
                    'dialplan_detail_tag' => 'action',
                    'dialplan_detail_type' => 'set',
                    'dialplan_detail_data' => 'default_' . $field . '=' . ($input[$field] ?? ''),
                    'dialplan_detail_inline' => 'true',
                    'dialplan_detail_group' => 0,
                    'dialplan_detail_order' => ($index + 2) * 5,
                ];
            }

            // This is the same write, XML generation, and after-commit cache path as Dialplan Manager.
            $this->dialplans->save([
                'domain_uuid' => $domain->domain_uuid,
                'dialplan_name' => $plan?->dialplan_name ?? 'DEFAULT_CALLER_ID',
                'dialplan_context' => $domain->domain_name,
                'dialplan_continue' => 'true',
                'dialplan_order' => $plan?->dialplan_order ?? 19,
                'dialplan_enabled' => 'true',
                'dialplan_destination' => $plan?->dialplan_destination ?? 'false',
                'dialplan_number' => $plan?->dialplan_number,
                'dialplan_description' => $plan?->dialplan_description,
                'dialplan_details' => $details,
            ], $plan ?? new Dialplans(['app_uuid' => self::APP_UUID]));
        });
    }

    private function plans(Domain $domain, bool $lock = false): Collection
    {
        return Dialplans::query()
            ->with(['dialplan_details' => fn ($q) => $q->orderBy('dialplan_detail_uuid')])
            ->where(fn ($q) => $q->where('app_uuid', self::APP_UUID)
                ->orWhereRaw('LOWER(dialplan_name) = ?', ['default_caller_id']))
            ->where(fn ($q) => $q->where('domain_uuid', $domain->domain_uuid)
                ->orWhere(fn ($q) => $q->whereNull('domain_uuid')
                    ->whereIn('dialplan_context', [$domain->domain_name, '${domain_name}', 'global'])))
            ->orderBy('dialplan_uuid')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();
    }

    private function revision(Collection $plans): string
    {
        return hash('sha256', $plans->map(fn ($plan) => [
            $plan->getAttributes(),
            $plan->dialplan_details->map(fn ($detail) => $detail->getAttributes())->all(),
        ])->toJson());
    }

    private function hasCustomDetails(Dialplans $plan): bool
    {
        // Do not discard custom builder rows, including disabled rules absent from XML.
        return $plan->dialplan_details->contains(function ($detail) {
            if ($detail->dialplan_detail_tag === 'condition') {
                return filled($detail->dialplan_detail_type) || filled($detail->dialplan_detail_data)
                    || filled($detail->dialplan_detail_break);
            }

            $variable = explode('=', (string) $detail->dialplan_detail_data, 2)[0];

            return $detail->dialplan_detail_tag !== 'action' || $detail->dialplan_detail_type !== 'set'
                || !in_array($variable, array_map(fn ($field) => 'default_' . $field, self::FIELDS), true);
        });
    }

    /**
     * Read executable XML, since saved XML can differ from builder detail rows.
     * Only a single unconditional rule of literal caller-ID assignments is safe
     * for this shortcut. Leave conditional/custom XML to the full editor.
     */
    private function readValues(Dialplans $plan): ?array
    {
        $xml = trim((string) $plan->dialplan_xml);
        if ($xml === '' || str_contains(strtoupper($xml), '<!DOCTYPE')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if ($root->tagName !== 'extension' || $root->getAttribute('continue') !== 'true') {
            return null;
        }
        foreach ($root->attributes as $attribute) {
            if (!in_array($attribute->name, ['name', 'continue', 'uuid'], true)) {
                return null;
            }
        }
        $conditions = $this->elements($root);
        if (count($conditions) !== 1 || $conditions[0]->tagName !== 'condition') {
            return null;
        }
        foreach ($conditions[0]->attributes as $attribute) {
            if (!in_array($attribute->name, ['field', 'expression'], true) || $attribute->value !== '') {
                return null;
            }
        }

        $values = array_fill_keys(self::FIELDS, null);
        $seen = [];
        foreach ($this->elements($conditions[0]) as $action) {
            if ($action->tagName !== 'action' || $action->getAttribute('application') !== 'set'
                || $action->getAttribute('inline') !== 'true' || $this->elements($action)) {
                return null;
            }
            foreach ($action->attributes as $attribute) {
                if (!in_array($attribute->name, ['application', 'data', 'inline'], true)) {
                    return null;
                }
            }
            $assignment = explode('=', $action->getAttribute('data'), 2);
            $field = preg_replace('/^default_/', '', $assignment[0]);
            if (count($assignment) !== 2 || $assignment[0] !== 'default_' . $field
                || !array_key_exists($field, $values) || isset($seen[$field])) {
                return null;
            }
            $value = $assignment[1];
            if (preg_match('/[$\x00-\x1f]/', $value)
                || (str_ends_with($field, '_number') && $value !== '' && !preg_match('/^\+?[0-9]{1,25}$/D', $value))) {
                return null;
            }
            $seen[$field] = true;
            $values[$field] = $value === '' ? null : $value;
        }

        return $values;
    }

    private function elements(DOMElement $element): array
    {
        return array_values(array_filter(iterator_to_array($element->childNodes), fn ($node) => $node instanceof DOMElement));
    }
}
