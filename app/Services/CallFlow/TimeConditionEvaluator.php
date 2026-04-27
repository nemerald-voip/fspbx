<?php

namespace App\Services\CallFlow;

use DateTimeImmutable;
use DateTimeZone;
use SimpleXMLElement;

/**
 * Evaluates a FusionPBX "time condition" dialplan's XML against a point in time.
 *
 * Time-condition dialplans are XML documents whose `<condition>` elements carry
 * one or more time attributes (`hour="9-17" wday="2-6"` etc.). FreeSWITCH runs
 * the first matching condition's actions; a trailing condition with no time
 * attributes acts as the fallthrough. We mirror that: return the action of the
 * first condition whose time predicates all match, plus the action of the
 * first subsequent condition with no time predicates as a documented fallback
 * so the simulator can emit both "what happens now" and "what the fallback is"
 * branches.
 */
class TimeConditionEvaluator
{
    /** Attribute names that carry time predicates. */
    private const TIME_FIELDS = [
        'year', 'mon', 'mday', 'yday', 'wday', 'week', 'mweek',
        'hour', 'minute', 'minute-of-day', 'time-of-day', 'date-time',
    ];

    /**
     * @return array{
     *     matched_action: ?array{destination_app: string, destination_data: ?string},
     *     fallback_action: ?array{destination_app: string, destination_data: ?string},
     *     matched_summary: ?string
     * }
     */
    public function evaluate(string $dialplanXml, DateTimeImmutable $at, string $timezone): array
    {
        $local = $at->setTimezone(new DateTimeZone($timezone ?: 'UTC'));

        $xml = $this->load($dialplanXml);

        $matchedAction = null;
        $matchedSummary = null;
        $fallbackAction = null;

        foreach ($xml->xpath('//condition') ?: [] as $condition) {
            $timeAttrs = $this->timeAttributes($condition);

            if ($timeAttrs === []) {
                // First unconditional condition = fallback; don't return early
                // because a time-conditional condition may appear above it.
                if ($fallbackAction === null) {
                    $fallbackAction = $this->firstTransferAction($condition);
                }
                continue;
            }

            if ($matchedAction !== null) {
                continue;
            }

            if ($this->conditionMatches($timeAttrs, $local)) {
                $matchedAction = $this->firstTransferAction($condition);
                $matchedSummary = $this->summarise($timeAttrs);
            }
        }

        return [
            'matched_action' => $matchedAction,
            'fallback_action' => $fallbackAction,
            'matched_summary' => $matchedSummary,
        ];
    }

    private function load(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $parsed = @simplexml_load_string($xml);
            if ($parsed === false) {
                throw new TimeConditionParseException('dialplan XML failed to parse');
            }
            return $parsed;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array<string, string>
     */
    private function timeAttributes(SimpleXMLElement $condition): array
    {
        $out = [];
        foreach (self::TIME_FIELDS as $field) {
            $value = (string) ($condition[$field] ?? '');
            if ($value !== '') {
                $out[$field] = $value;
            }
        }
        return $out;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function conditionMatches(array $attrs, DateTimeImmutable $local): bool
    {
        foreach ($attrs as $field => $expression) {
            if (! $this->fieldMatches($field, $expression, $local)) {
                return false;
            }
        }
        return true;
    }

    private function fieldMatches(string $field, string $expression, DateTimeImmutable $local): bool
    {
        switch ($field) {
            case 'year':
                return $this->matchIntRangeList((int) $local->format('Y'), $expression);
            case 'mon':
                return $this->matchIntRangeList((int) $local->format('n'), $expression);
            case 'mday':
                return $this->matchIntRangeList((int) $local->format('j'), $expression);
            case 'yday':
                // PHP `z` is 0-indexed (0=Jan 1); FreeSWITCH yday is 1-indexed.
                return $this->matchIntRangeList(((int) $local->format('z')) + 1, $expression);
            case 'wday':
                // FreeSWITCH wday: 1=Sun…7=Sat. PHP `w` is 0=Sun…6=Sat.
                return $this->matchIntRangeList(((int) $local->format('w')) + 1, $expression);
            case 'week':
                return $this->matchIntRangeList((int) $local->format('W'), $expression);
            case 'mweek':
                return $this->matchIntRangeList((int) ceil(((int) $local->format('j')) / 7), $expression);
            case 'hour':
                return $this->matchIntRangeList((int) $local->format('G'), $expression);
            case 'minute':
                return $this->matchIntRangeList((int) $local->format('i'), $expression);
            case 'minute-of-day':
                $mod = ((int) $local->format('G')) * 60 + (int) $local->format('i') + 1;
                return $this->matchIntRangeList($mod, $expression);
            case 'time-of-day':
                return $this->matchTimeOfDay($local, $expression);
            case 'date-time':
                return $this->matchDateTime($local, $expression);
        }
        return false;
    }

    /**
     * Matches integer values against FreeSWITCH range-list expressions.
     * Accepts "5", "1-5", "1,3,5", "1-5,7,9-11". Supports overnight wrap
     * (e.g. "22-6") for fields where that makes sense.
     */
    private function matchIntRangeList(int $value, string $expression): bool
    {
        foreach (explode(',', $expression) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, '-')) {
                [$a, $b] = array_map('trim', explode('-', $part, 2));
                if ($a === '' || $b === '' || ! is_numeric($a) || ! is_numeric($b)) {
                    continue;
                }
                $lo = (int) $a;
                $hi = (int) $b;
                if ($lo <= $hi) {
                    if ($value >= $lo && $value <= $hi) {
                        return true;
                    }
                } else {
                    // Overnight wrap: e.g. 22-6 means 22,23,0,1,2,3,4,5,6.
                    if ($value >= $lo || $value <= $hi) {
                        return true;
                    }
                }
            } else {
                if (is_numeric($part) && $value === (int) $part) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Matches `time-of-day` expressions like "09:00-17:30". Supports overnight
     * wrap (e.g. "22:00-06:00") by treating wrap as "lo..end-of-day OR 0..hi".
     */
    private function matchTimeOfDay(DateTimeImmutable $local, string $expression): bool
    {
        $parts = explode('-', $expression, 2);
        if (count($parts) !== 2) {
            return false;
        }
        $loMin = $this->timeOfDayToMinutes(trim($parts[0]));
        $hiMin = $this->timeOfDayToMinutes(trim($parts[1]));
        if ($loMin === null || $hiMin === null) {
            return false;
        }
        $nowMin = ((int) $local->format('G')) * 60 + (int) $local->format('i');

        if ($loMin <= $hiMin) {
            return $nowMin >= $loMin && $nowMin <= $hiMin;
        }
        return $nowMin >= $loMin || $nowMin <= $hiMin;
    }

    private function timeOfDayToMinutes(string $hhmm): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
            return null;
        }
        return $h * 60 + $min;
    }

    /**
     * Matches `date-time` expressions like "2026-04-23 09:00~2026-04-23 17:00".
     * Evaluated in the same timezone as $local.
     */
    private function matchDateTime(DateTimeImmutable $local, string $expression): bool
    {
        $parts = explode('~', $expression, 2);
        if (count($parts) !== 2) {
            return false;
        }
        try {
            $from = new DateTimeImmutable(trim($parts[0]), $local->getTimezone());
            $to = new DateTimeImmutable(trim($parts[1]), $local->getTimezone());
        } catch (\Throwable $e) {
            return false;
        }
        return $local >= $from && $local <= $to;
    }

    private function firstTransferAction(SimpleXMLElement $condition): ?array
    {
        foreach ($condition->children() as $child) {
            if ($child->getName() !== 'action') {
                continue;
            }
            $app = (string) ($child['application'] ?? '');
            if ($app === '' || $app === 'set' || $app === 'export' || $app === 'log') {
                continue;
            }
            return [
                'destination_app' => $app,
                'destination_data' => (string) ($child['data'] ?? ''),
            ];
        }
        return null;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function summarise(array $attrs): string
    {
        $bits = [];
        foreach ($attrs as $field => $expression) {
            $bits[] = "{$field}={$expression}";
        }
        return implode(' ', $bits);
    }
}
