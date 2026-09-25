import { trans } from '@i18n';

export function queueStatusLabel(value) {
    return {
        Available: trans('Available'),
        'Available (On Demand)': trans('Available (On Demand)'),
        'On Break': trans('On Break'),
        'Logged Out': trans('Logged Out'),
        Waiting: trans('Waiting'),
        Receiving: trans('Receiving a Call'),
        'In a queue call': trans('On a Call'),
        Idle: trans('Idle'),
        Reserved: trans('Reserved'),
        Trying: trans('Trying'),
        Answered: trans('Answered'),
        Abandoned: trans('Abandoned'),
    }[value] ?? value ?? '-';
}

export function queueStrategyLabel(value) {
    return {
        'ring-all': trans('Ring All'),
        'longest-idle-agent': trans('Longest Idle Agent'),
        'round-robin': trans('Round Robin'),
        'top-down': trans('Top Down'),
        'agent-with-least-talk-time': trans('Least Talk Time'),
        'agent-with-fewest-calls': trans('Fewest Calls'),
        'sequentially-by-agent-order': trans('Sequential Agent Order'),
        random: trans('Random'),
    }[value] ?? value ?? '-';
}
