import { displayLocale } from '@/lib/hydration';

/**
 * Helpers for showing bookable times. Times arrive as UTC instants and are
 * shown in whichever zone the customer picks: their own, or the business's.
 */

const timeFormats = new Map<string, Intl.DateTimeFormat>();

function timeFormat(zone: string): Intl.DateTimeFormat {
    const locale = displayLocale();
    const key = `${locale ?? ''}|${zone}`;
    let format = timeFormats.get(key);

    if (!format) {
        format = new Intl.DateTimeFormat(locale, {
            hour: 'numeric',
            minute: '2-digit',
            timeZone: zone,
        });
        timeFormats.set(key, format);
    }

    return format;
}

/** "2026-11-03T10:30:00Z" → "10:30 AM" (or "10:30") in a zone. */
export function timeIn(instant: string | Date, zone: string): string {
    return timeFormat(zone).format(
        typeof instant === 'string' ? new Date(instant) : instant,
    );
}

/** The clock in a zone as a fractional hour: 10:30 → 10.5. */
export function hourIn(instant: string | Date, zone: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        minute: 'numeric',
        hourCycle: 'h23',
        timeZone: zone,
    }).formatToParts(typeof instant === 'string' ? new Date(instant) : instant);
    const part = (type: string) =>
        Number(parts.find((p) => p.type === type)?.value ?? 0);

    return part('hour') + part('minute') / 60;
}

/** "Europe/London" → "London", "America/New_York" → "New York". */
export function zoneCity(zone: string): string {
    return (zone.split('/').pop() ?? zone).replaceAll('_', ' ');
}

export type DayPart = 'Morning' | 'Afternoon' | 'Evening';

export function dayPart(hour: number): DayPart {
    if (hour < 12) {
        return 'Morning';
    }

    return hour < 17 ? 'Afternoon' : 'Evening';
}

const dateFormats = new Map<string, Intl.DateTimeFormat>();

/** The calendar date a moment falls on in a zone, as "YYYY-MM-DD". */
export function dateIn(instant: string | Date, zone: string): string {
    let format = dateFormats.get(zone);

    if (!format) {
        format = new Intl.DateTimeFormat('en-CA', {
            timeZone: zone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
        dateFormats.set(zone, format);
    }

    return format.format(
        typeof instant === 'string' ? new Date(instant) : instant,
    );
}

/**
 * Minutes since midnight on `date`, as the clock reads in `zone`. Moments
 * before that day give 0 and after it 1440, so blocks clip to the day.
 */
export function minutesOnDay(
    instant: string,
    date: string,
    zone: string,
): number {
    const day = dateIn(instant, zone);

    if (day < date) {
        return 0;
    }

    if (day > date) {
        return 24 * 60;
    }

    return Math.round(hourIn(instant, zone) * 60);
}
