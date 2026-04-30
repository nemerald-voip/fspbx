<?php

namespace App\Services;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\FusionCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DialplanService
{
    private const TIME_CONDITION_FIELDS = [
        'hour',
        'minute',
        'minute-of-day',
        'mday',
        'mweek',
        'mon',
        'time-of-day',
        'yday',
        'year',
        'wday',
        'week',
        'date-time',
    ];

    private const DANGEROUS_APPLICATIONS = [
        'system',
        'bgsystem',
        'spawn',
        'bg_spawn',
        'spawn_stream',
    ];

    public function save(array $validated, ?Dialplans $dialplan = null): Dialplans
    {
        return DB::transaction(function () use ($validated, $dialplan) {
            $dialplan ??= new Dialplans();
            $isNew = !$dialplan->exists;
            $dialplanUuid = $dialplan->dialplan_uuid ?: (string) Str::uuid();
            $xmlMode = ($validated['editor_mode'] ?? 'builder') === 'xml';
            $details = $xmlMode ? [] : $this->normalizedDetails($validated['dialplan_details'] ?? []);

            $data = [
                'dialplan_uuid' => $dialplanUuid,
                'domain_uuid' => blank($validated['domain_uuid'] ?? null) ? null : $validated['domain_uuid'],
                'hostname' => $this->blankToNull($validated['hostname'] ?? null),
                'dialplan_name' => $this->sanitizeName($validated['dialplan_name']),
                'dialplan_number' => $this->blankToNull($validated['dialplan_number'] ?? null),
                'dialplan_destination' => $validated['dialplan_destination'] ?? 'false',
                'dialplan_context' => $validated['dialplan_context'],
                'dialplan_continue' => $validated['dialplan_continue'],
                'dialplan_order' => $validated['dialplan_order'],
                'dialplan_enabled' => $validated['dialplan_enabled'],
                'dialplan_description' => $this->blankToNull($validated['dialplan_description'] ?? null),
            ];

            if ($isNew) {
                $data['app_uuid'] = (string) Str::uuid();
                $data['insert_date'] = now();
                $data['insert_user'] = session('user_uuid');
            } else {
                $data['update_date'] = now();
                $data['update_user'] = session('user_uuid');
            }

            $dialplan->forceFill($data);
            $dialplan->dialplan_xml = $xmlMode
                ? trim((string) ($validated['dialplan_xml'] ?? ''))
                : $this->buildXml($dialplan, $details);
            $dialplan->save();

            if (!$xmlMode) {
                $dialplan->dialplan_details()->delete();
                $this->createDetails($dialplan, $details);
            }

            $this->clearDialplanCache($dialplan->dialplan_context);

            return $dialplan;
        });
    }

    public function copy(Dialplans $dialplan): Dialplans
    {
        return DB::transaction(function () use ($dialplan) {
            $copy = $dialplan->replicate();
            $copy->dialplan_uuid = (string) Str::uuid();
            $copy->dialplan_name = $this->copyName($dialplan->dialplan_name);
            $copy->dialplan_description = trim((string) $dialplan->dialplan_description . ' (copy)') ?: null;
            $copy->insert_date = now();
            $copy->insert_user = session('user_uuid');
            $copy->update_date = null;
            $copy->update_user = null;
            $copy->dialplan_xml = null;
            $copy->save();

            $details = $dialplan->dialplan_details()
                ->orderBy('dialplan_detail_group')
                ->orderBy('dialplan_detail_order')
                ->get()
                ->map(fn (DialplanDetails $detail) => $this->detailToArray($detail))
                ->values()
                ->all();

            $copy->dialplan_xml = $this->buildXml($copy, $details);
            $copy->save();
            $this->createDetails($copy, $details);
            $this->clearDialplanCache($copy->dialplan_context);

            return $copy;
        });
    }

    public function toggle(Collection $dialplans): void
    {
        DB::transaction(function () use ($dialplans) {
            $dialplans->each(function (Dialplans $dialplan) {
                $dialplan->dialplan_enabled = $dialplan->dialplan_enabled === true ? 'false' : 'true';
                $dialplan->update_date = now();
                $dialplan->update_user = session('user_uuid');
                $dialplan->save();
                $this->clearDialplanCache($dialplan->dialplan_context);
            });
        });
    }

    public function delete(Collection $dialplans): void
    {
        DB::transaction(function () use ($dialplans) {
            $dialplans->each(function (Dialplans $dialplan) {
                $context = $dialplan->dialplan_context;
                $dialplan->dialplan_details()->delete();
                $dialplan->delete();
                $this->clearDialplanCache($context);
            });
        });
    }

    public function normalizedDetails(array $details): array
    {
        return collect($details)
            ->map(function ($detail) {
                return [
                    'dialplan_detail_uuid' => $this->blankToNull($detail['dialplan_detail_uuid'] ?? null),
                    'dialplan_detail_tag' => $this->blankToNull($detail['dialplan_detail_tag'] ?? null),
                    'dialplan_detail_type' => $this->blankToNull($detail['dialplan_detail_type'] ?? null),
                    'dialplan_detail_data' => $this->blankToNull($detail['dialplan_detail_data'] ?? null),
                    'dialplan_detail_break' => $this->blankToNull($detail['dialplan_detail_break'] ?? null),
                    'dialplan_detail_inline' => $this->booleanStringOrNull($detail['dialplan_detail_inline'] ?? null),
                    'dialplan_detail_group' => (int) ($detail['dialplan_detail_group'] ?? 0),
                    'dialplan_detail_order' => (int) ($detail['dialplan_detail_order'] ?? 0),
                    'dialplan_detail_enabled' => $this->booleanString($detail['dialplan_detail_enabled'] ?? 'true', true),
                ];
            })
            ->filter(fn ($detail) => filled($detail['dialplan_detail_tag']))
            ->sortBy([
                ['dialplan_detail_group', 'asc'],
                ['dialplan_detail_order', 'asc'],
            ])
            ->values()
            ->all();
    }

    public function buildXml(Dialplans $dialplan, array $details): string
    {
        $groups = collect($details)
            ->filter(fn ($detail) => $this->booleanValue($detail['dialplan_detail_enabled'] ?? 'true', true))
            ->groupBy('dialplan_detail_group')
            ->sortKeys();

        $lines = [
            sprintf(
                '<extension name="%s" continue="%s" uuid="%s">',
                $this->xml($dialplan->dialplan_name),
                $this->xml($dialplan->dialplan_continue ?: 'false'),
                $this->xml($dialplan->dialplan_uuid)
            ),
        ];

        foreach ($groups as $groupDetails) {
            $conditions = $groupDetails
                ->filter(fn ($detail) => in_array($detail['dialplan_detail_tag'], ['condition', 'regex'], true))
                ->values();

            $actions = $groupDetails
                ->filter(fn ($detail) => in_array($detail['dialplan_detail_tag'], ['action', 'anti-action'], true))
                ->values();

            if ($conditions->isEmpty()) {
                $lines[] = "\t<condition field=\"\" expression=\"\">";
                foreach ($actions as $detail) {
                    $lines[] = $this->actionXml($detail);
                }
                $lines[] = "\t</condition>";
                continue;
            }

            if ($actions->isEmpty()) {
                if ($this->isTimeGroup($conditions)) {
                    $lines[] = $this->selfClosingTimeConditionXml($conditions);
                    continue;
                }

                foreach ($conditions as $detail) {
                    $lines[] = $this->conditionXml($detail, true);
                }
                continue;
            }

            if ($this->isRegexGroup($conditions)) {
                $lines[] = $this->openRegexConditionXml($conditions->first());

                foreach ($conditions->skip(1) as $detail) {
                    $lines[] = $this->regexXml($detail);
                }
                $this->appendPublicInboundDefaults($lines, $dialplan, $actions);
                foreach ($actions as $detail) {
                    $lines[] = $this->actionXml($detail);
                }
                $lines[] = "\t</condition>";
                continue;
            }

            if ($this->isTimeGroup($conditions)) {
                $lines[] = $this->openTimeConditionXml($conditions);
                $this->appendPublicInboundDefaults($lines, $dialplan, $actions);
                foreach ($actions as $detail) {
                    $lines[] = $this->actionXml($detail);
                }
                $lines[] = "\t</condition>";
                continue;
            }

            $lastCondition = $conditions->pop();
            foreach ($conditions as $detail) {
                $lines[] = $this->conditionXml($detail, true);
            }
            $lines[] = $this->conditionXml($lastCondition, false);
            $this->appendPublicInboundDefaults($lines, $dialplan, $actions);
            foreach ($actions as $detail) {
                $lines[] = $this->actionXml($detail);
            }
            $lines[] = "\t</condition>";
        }

        $lines[] = '</extension>';

        return implode("\n", $lines);
    }

    public function containsDangerousApplication(?string $value): bool
    {
        $value = strtolower((string) $value);

        foreach (self::DANGEROUS_APPLICATIONS as $application) {
            if (str_contains($value, $application)) {
                return true;
            }
        }

        return false;
    }

    public function validateXml(string $xml): array
    {
        $xml = trim($xml);
        $errors = [];

        if ($xml === '') {
            return ['XML is required.'];
        }

        if ($this->containsDangerousXml($xml)) {
            $errors[] = 'This XML contains a FreeSWITCH application that is not allowed.';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);

        if (!$loaded) {
            $firstError = libxml_get_errors()[0] ?? null;
            $errors[] = $firstError
                ? trim("XML is invalid: line {$firstError->line}, {$firstError->message}")
                : 'XML is invalid.';
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $errors;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document->documentElement?->tagName !== 'extension') {
            $errors[] = 'Dialplan XML must use an extension element as the root node.';
        }

        foreach (['action', 'anti-action'] as $tagName) {
            foreach ($document->getElementsByTagName($tagName) as $node) {
                if ($this->containsDangerousApplication($node->getAttribute('application'))) {
                    $errors[] = 'This XML contains a FreeSWITCH application that is not allowed.';
                    break 2;
                }
            }
        }

        return array_values(array_unique($errors));
    }

    public function clearDialplanCache(?string $context): void
    {
        $context = in_array($context, ['${domain_name}', 'global'], true) ? '*' : $context;
        FusionCache::clear('dialplan:' . ($context ?: '*'));
    }

    private function createDetails(Dialplans $dialplan, array $details): void
    {
        foreach ($details as $detail) {
            DialplanDetails::create([
                'dialplan_detail_uuid' => (string) Str::uuid(),
                'domain_uuid' => $dialplan->domain_uuid,
                'dialplan_uuid' => $dialplan->dialplan_uuid,
                'dialplan_detail_tag' => $detail['dialplan_detail_tag'],
                'dialplan_detail_type' => $detail['dialplan_detail_type'],
                'dialplan_detail_data' => $detail['dialplan_detail_data'],
                'dialplan_detail_break' => $detail['dialplan_detail_break'],
                'dialplan_detail_inline' => $detail['dialplan_detail_inline'],
                'dialplan_detail_group' => $detail['dialplan_detail_group'],
                'dialplan_detail_order' => $detail['dialplan_detail_order'],
                'dialplan_detail_enabled' => $detail['dialplan_detail_enabled'],
            ]);
        }
    }

    private function detailToArray(DialplanDetails $detail): array
    {
        return [
            'dialplan_detail_tag' => $detail->getRawOriginal('dialplan_detail_tag'),
            'dialplan_detail_type' => $detail->getRawOriginal('dialplan_detail_type'),
            'dialplan_detail_data' => $detail->getRawOriginal('dialplan_detail_data'),
            'dialplan_detail_break' => $detail->getRawOriginal('dialplan_detail_break'),
            'dialplan_detail_inline' => $this->booleanStringOrNull($detail->getRawOriginal('dialplan_detail_inline')),
            'dialplan_detail_group' => (int) $detail->getRawOriginal('dialplan_detail_group'),
            'dialplan_detail_order' => (int) $detail->getRawOriginal('dialplan_detail_order'),
            'dialplan_detail_enabled' => $this->booleanString($detail->getRawOriginal('dialplan_detail_enabled') ?? 'true', true),
        ];
    }

    private function isRegexGroup(Collection $conditions): bool
    {
        $first = $conditions->first();

        return ($first['dialplan_detail_tag'] ?? null) === 'condition'
            && ($first['dialplan_detail_type'] ?? null) === 'regex';
    }

    private function isTimeGroup(Collection $conditions): bool
    {
        return $conditions->isNotEmpty()
            && $conditions->every(fn ($detail) => in_array($detail['dialplan_detail_type'], self::TIME_CONDITION_FIELDS, true));
    }

    private function conditionXml(array $detail, bool $selfClosing): string
    {
        $break = filled($detail['dialplan_detail_break']) ? ' break="' . $this->xml($detail['dialplan_detail_break']) . '"' : '';
        $end = $selfClosing ? '/>' : '>';

        return sprintf(
            "\t<condition field=\"%s\" expression=\"%s\"%s%s",
            $this->xml($detail['dialplan_detail_type']),
            $this->xml($detail['dialplan_detail_data']),
            $break,
            $end
        );
    }

    private function openRegexConditionXml(array $detail): string
    {
        $break = filled($detail['dialplan_detail_break']) ? ' break="' . $this->xml($detail['dialplan_detail_break']) . '"' : '';

        return sprintf(
            "\t<condition regex=\"%s\"%s>",
            $this->xml($detail['dialplan_detail_data'] ?: 'all'),
            $break
        );
    }

    private function regexXml(array $detail): string
    {
        return sprintf(
            "\t\t<regex field=\"%s\" expression=\"%s\"/>",
            $this->xml($detail['dialplan_detail_type']),
            $this->xml($detail['dialplan_detail_data'])
        );
    }

    private function openTimeConditionXml(Collection $conditions): string
    {
        $attributes = $conditions
            ->map(fn ($detail) => sprintf('%s="%s"', $detail['dialplan_detail_type'], $this->xml($detail['dialplan_detail_data'])))
            ->implode(' ');

        $break = filled($conditions->last()['dialplan_detail_break'] ?? null)
            ? ' break="' . $this->xml($conditions->last()['dialplan_detail_break']) . '"'
            : '';

        return "\t<condition {$attributes}{$break}>";
    }

    private function selfClosingTimeConditionXml(Collection $conditions): string
    {
        $attributes = $conditions
            ->map(fn ($detail) => sprintf('%s="%s"', $detail['dialplan_detail_type'], $this->xml($detail['dialplan_detail_data'])))
            ->implode(' ');

        $break = filled($conditions->last()['dialplan_detail_break'] ?? null)
            ? ' break="' . $this->xml($conditions->last()['dialplan_detail_break']) . '"'
            : '';

        return "\t<condition {$attributes}{$break}/>";
    }

    private function actionXml(array $detail): string
    {
        $tag = $detail['dialplan_detail_tag'] === 'anti-action' ? 'anti-action' : 'action';
        $inline = filled($detail['dialplan_detail_inline']) ? ' inline="' . $this->xml($detail['dialplan_detail_inline']) . '"' : '';

        return sprintf(
            "\t\t<%s application=\"%s\" data=\"%s\"%s/>",
            $tag,
            $this->xml($detail['dialplan_detail_type']),
            $this->xml($detail['dialplan_detail_data']),
            $inline
        );
    }

    private function appendPublicInboundDefaults(array &$lines, Dialplans $dialplan, Collection $actions): void
    {
        if ($actions->isEmpty() || !$this->isPublicContext($dialplan->dialplan_context)) {
            return;
        }

        $lines[] = "\t\t<action application=\"export\" data=\"call_direction=inbound\" inline=\"true\"/>";

        if (filled($dialplan->domain_uuid)) {
            $lines[] = "\t\t<action application=\"set\" data=\"domain_uuid={$this->xml($dialplan->domain_uuid)}\" inline=\"true\"/>";
        }

        $domainName = filled($dialplan->domain_uuid)
            ? Domain::query()->whereKey($dialplan->domain_uuid)->value('domain_name')
            : null;

        if (filled($domainName)) {
            $lines[] = "\t\t<action application=\"set\" data=\"domain_name={$this->xml($domainName)}\" inline=\"true\"/>";
        }
    }

    private function isPublicContext(?string $context): bool
    {
        return $context === 'public'
            || str_starts_with((string) $context, 'public@')
            || str_ends_with((string) $context, '.public');
    }

    private function sanitizeName(string $value): string
    {
        return str_replace([' ', '/'], ['_', ''], trim($value));
    }

    private function copyName(?string $name): string
    {
        return Str::limit(trim((string) $name . '_copy'), 255, '');
    }

    private function blankToNull($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return blank($value) ? null : (string) $value;
    }

    private function booleanString($value, bool $default = false): string
    {
        return $this->booleanValue($value, $default) ? 'true' : 'false';
    }

    private function booleanStringOrNull($value): ?string
    {
        return blank($value) ? null : $this->booleanString($value);
    }

    private function booleanValue($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function containsDangerousXml(string $xml): bool
    {
        foreach (self::DANGEROUS_APPLICATIONS as $application) {
            $application = preg_quote($application, '/');

            if (preg_match("/([\"']){$application}([\"'])/i", $xml)) {
                return true;
            }

            if (preg_match("/\\{{$application}(?:[\\s}:]|$)/i", $xml)) {
                return true;
            }
        }

        return false;
    }

    private function xml($value): string
    {
        return str_replace('&gt;', '>', htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }
}
