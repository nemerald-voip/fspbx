import { currentLocale, trans } from '@i18n';

export function formatDuration(value) {
    if (value === null || value === undefined || value === '') return '-';
    const seconds = Math.max(0, Math.floor(Number(value) || 0));
    const unit = (number, name) => new Intl.NumberFormat(currentLocale.value, {
        style: 'unit', unit: name, unitDisplay: 'short',
    }).format(number);

    if (seconds < 60) return unit(seconds, 'second');
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${unit(minutes, 'minute')} ${unit(seconds % 60, 'second')}`;
    return `${unit(Math.floor(minutes / 60), 'hour')} ${unit(minutes % 60, 'minute')}`;
}

export function relativeTime(value) {
    if (!value) return '';
    const timestamp = new Date(value).getTime();
    if (Number.isNaN(timestamp)) return '';
    const seconds = Math.round((timestamp - Date.now()) / 1000);
    if (Math.abs(seconds) < 5) return trans('just now');
    const formatter = new Intl.RelativeTimeFormat(currentLocale.value, { numeric: 'always', style: 'short' });
    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second');
    if (Math.abs(seconds) < 3600) return formatter.format(Math.round(seconds / 60), 'minute');
    if (Math.abs(seconds) < 86400) return formatter.format(Math.round(seconds / 3600), 'hour');
    return formatter.format(Math.round(seconds / 86400), 'day');
}

export function weekdayLabel(day) {
    // January 1, 2024 was a Monday; weekdays use ISO numbers 1–7.
    return new Intl.DateTimeFormat(currentLocale.value, { weekday: 'short', timeZone: 'UTC' })
        .format(new Date(Date.UTC(2024, 0, day)));
}

export function timeOfDay(value) {
    if (!value) return '';
    const match = String(value).match(/^(\d{1,2}):(\d{2})/);
    if (!match) return String(value);
    return new Intl.DateTimeFormat(currentLocale.value, {
        hour: 'numeric', minute: '2-digit', timeZone: 'UTC',
    }).format(new Date(Date.UTC(2024, 0, 1, Number(match[1]), Number(match[2]))));
}
