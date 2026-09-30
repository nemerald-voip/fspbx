<?php

namespace App\Services;

/** The effective row status used by CDR display, exports and status filters. */
final class CdrStatus
{
    public static function fromAttributes(array $attributes): ?string
    {
        $status = $attributes['status'] ?? null;
        if ($status === 'callback_requested') {
            return $status;
        }
        $queueOutcome = QueueCallOutcome::fromAttributes($attributes);
        if ($queueOutcome !== null) {
            return $queueOutcome;
        }
        if (filter_var($attributes['voicemail_message'] ?? false, FILTER_VALIDATE_BOOLEAN) === false
            && filter_var($attributes['missed_call'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ($attributes['hangup_cause'] ?? null) === 'NORMAL_CLEARING') {
            return ($attributes['cc_cause'] ?? null) === 'cancel' && ($attributes['cc_cancel_reason'] ?? null) === 'BREAK_OUT'
                ? 'abandoned' : 'missed call';
        }

        return $status;
    }

    public static function sql(): string
    {
        $outcome = QueueCallOutcome::sql();

        return "CASE
            WHEN v_xml_cdr.status = 'callback_requested' THEN v_xml_cdr.status
            WHEN ({$outcome}) IS NOT NULL THEN ({$outcome})
            WHEN (v_xml_cdr.voicemail_message = false OR v_xml_cdr.voicemail_message IS NULL) AND v_xml_cdr.missed_call = true
                AND v_xml_cdr.hangup_cause = 'NORMAL_CLEARING' THEN
                CASE WHEN v_xml_cdr.cc_cause = 'cancel' AND v_xml_cdr.cc_cancel_reason = 'BREAK_OUT'
                    THEN 'abandoned' ELSE 'missed call' END
            ELSE v_xml_cdr.status END";
    }
}
