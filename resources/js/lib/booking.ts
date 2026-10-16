/**
 * Helpers for showing bookable times. Times arrive as UTC instants and are
 * shown in whichever zone the customer picks: their own, or the business's.
 */

const timeFormats = new Map<string, Intl.DateTimeFormat>();

function timeFormat(zone: string): Intl.DateTimeFormat {
    let format = timeFormats.get(zone);

    if (!format) {
        format = new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
            timeZone: zone,
        });
        timeFormats.set(zone, format);
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
