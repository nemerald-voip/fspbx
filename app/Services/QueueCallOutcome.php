<?php

namespace App\Services;

/** Classifies the queue member, independently of any later fallback or voicemail. */
final class QueueCallOutcome
{
    public static function fromAttributes(array $attributes): ?string
    {
        if (($attributes['cc_side'] ?? null) !== 'member') {
            return null;
        }
        if (($attributes['status'] ?? null) === 'callback_requested') {
            return 'callback_requested';
        }
        if (($attributes['cc_agent_bridged'] ?? null) === 'true') {
            return 'answered';
        }
        $cause = $attributes['cc_cause'] ?? null;
        $reason = $attributes['cc_cancel_reason'] ?? null;
        // Callback re-entry can enforce its original deadline with an internal
        // D digit, then set cc_cause=TIMEOUT while retaining EXIT_WITH_KEY.
        if ($cause === 'TIMEOUT' || ($cause === 'cancel' && in_array($reason, ['TIMEOUT', 'NO_AGENT_TIMEOUT'], true))) {
            return 'queue_timeout';
        }
        if ($cause === 'cancel' && $reason === 'EXIT_WITH_KEY') {
            return 'queue_exited';
        }
        if ($cause === 'cancel' && $reason === 'BREAK_OUT' && ($attributes['hangup_cause'] ?? null) === 'NORMAL_CLEARING') {
            return 'abandoned';
        }

        return 'queue_other';
    }

    public static function label(?string $outcome): ?string
    {
        return match ($outcome) {
            'answered' => __('Answered'),
            'abandoned' => __('Abandoned'),
            'queue_exited' => __('Exited queue'),
            'queue_timeout' => __('Timed out'),
            'callback_requested' => __('Callback requested'),
            'queue_other' => __('Other queue outcome'),
            default => null,
        };
    }

    public static function reason(array $attributes): ?string
    {
        return match (self::fromAttributes($attributes)) {
            'abandoned' => __('Caller disconnected while waiting'),
            'queue_exited' => __('The caller pressed the exit key'),
            'queue_timeout' => ($attributes['cc_cancel_reason'] ?? null) === 'NO_AGENT_TIMEOUT'
                ? __('No-agent timeout') : __('Queue timeout reached'),
            'queue_other' => ($attributes['cc_cause'] ?? null) === 'answered'
                ? __('Unverified queue answer') : __('Missing or unclassified queue outcome'),
            default => null,
        };
    }

    /** SQL mirrors fromAttributes; dashboard admission is verified by its scoped receipt join. */
    public static function sql(string $table = 'v_xml_cdr', ?string $callback = null): string
    {
        $callback ??= "{$table}.status = 'callback_requested'";

        return "CASE
            WHEN COALESCE({$table}.cc_side, '') <> 'member' THEN NULL
            WHEN {$callback} THEN 'callback_requested'
            WHEN {$table}.cc_agent_bridged = 'true' THEN 'answered'
            WHEN {$table}.cc_cause = 'TIMEOUT'
                OR ({$table}.cc_cause = 'cancel' AND {$table}.cc_cancel_reason IN ('TIMEOUT', 'NO_AGENT_TIMEOUT')) THEN 'queue_timeout'
            WHEN {$table}.cc_cause = 'cancel' AND {$table}.cc_cancel_reason = 'EXIT_WITH_KEY' THEN 'queue_exited'
            WHEN {$table}.cc_cause = 'cancel' AND {$table}.cc_cancel_reason = 'BREAK_OUT'
                AND {$table}.hangup_cause = 'NORMAL_CLEARING' THEN 'abandoned'
            ELSE 'queue_other' END";
    }
}
