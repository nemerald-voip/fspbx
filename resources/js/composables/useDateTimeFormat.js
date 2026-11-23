import { usePage } from '@inertiajs/vue3';

// Moment.js formats for the account's date/time notation (domain > date_format / time_format).
// Falls back to US notation, the historical default.
const DATE = { us: 'MMM D, YYYY', eu: 'DD/MM/YYYY', iso: 'YYYY-MM-DD' };

export function useDateTimeFormat() {
    const page = usePage();
    const current = () => page.props.dateTimeFormats ?? { date_format: 'us', time_format: '12h' };

    const date = () => DATE[current().date_format] ?? DATE.us;
    const time = (seconds = true) => current().time_format === '24h'
        ? (seconds ? 'HH:mm:ss' : 'HH:mm')
        : (seconds ? 'h:mm:ss A' : 'h:mm A');
    // US keeps "time date" order, eu/iso put the date first (same as local_datetime_format() in PHP).
    const dateTime = (seconds = true) => current().date_format === 'us'
        ? `${time(seconds)} ${date()}`
        : `${date()} ${time(seconds)}`;
    // Always "date time", for screens that already showed the date first.
    const dateThenTime = (seconds = true) => `${date()} ${time(seconds)}`;

    return { date, time, dateTime, dateThenTime };
}
