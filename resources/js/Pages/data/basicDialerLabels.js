import { trans } from '@i18n';

export function dialerLabel(value) {
    return {
        unknown: trans('Unknown'),
        draft: trans('Draft'),
        running: trans('Running'),
        paused: trans('Paused'),
        stopped: trans('Stopped'),
        completed: trans('Completed'),
        pending: trans('Pending'),
        dialing: trans('Dialing'),
        retry_wait: trans('Retry Wait'),
        answered: trans('Answered'),
        failed: trans('Failed'),
        queued: trans('Queued'),
        rejected: trans('Rejected'),
        no_answer: trans('No Answer'),
        missing_caller_id: trans('Missing caller ID'),
        no_outbound_route: trans('No outbound route'),
        originate_rejected: trans('Call rejected'),
        esl_error: trans('Event socket error'),
    }[value] ?? value ?? '';
}

// Stored diagnostics and protocol outcomes remain unchanged; localize known UI messages.
export function dialerError(value) {
    return {
        'Campaign caller ID number is required.': trans('Campaign caller ID number is required.'),
        'No outbound route matched recipient.': trans('No outbound route matched recipient.'),
    }[value] ?? value ?? '';
}
