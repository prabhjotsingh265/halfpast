import { displayLocale } from '@/lib/hydration';

/** ISO weekday (1 is Monday) => stretches of local clock time, "09:00" to "17:30". */
export type WeekHours = Record<number, [string, string][]>;

/** "09:30" → 9.5 */
export function clockHours(clock: string): number {
    const [hours, minutes] = clock.split(':').map(Number);

    return hours + minutes / 60;
}

/** The weekday and clock in a zone at a moment: ISO weekday, fractional hours. */
export function localDay(
    at: Date,
    zone: string,
): { weekday: number; hour: number } {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            weekday: 'short',
            hour: 'numeric',
            minute: 'numeric',
            hourCycle: 'h23',
            timeZone: zone,
        })
            .formatToParts(at)
            .map((part) => [part.type, part.value]),
    );
    const weekday =
        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(
            parts.weekday,
        ) + 1;

    return { weekday, hour: Number(parts.hour) + Number(parts.minute) / 60 };
}

/**
 * A local clock time as the reader writes times: "9:30 am" or "09:30".
 * It's a time of day, not a moment, so no zone is involved.
 */
export function clockLabel(clock: string): string {
    const [hours, minutes] = clock.split(':').map(Number);

    return new Intl.DateTimeFormat(displayLocale(), {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(2026, 0, 5, hours % 24, minutes)));
}

/** Short weekday names in the reader's language, Monday first. */
export function weekdayNames(): string[] {
    const format = new Intl.DateTimeFormat(displayLocale(), {
        weekday: 'short',
        timeZone: 'UTC',
    });

    // 5 January 2026 was a Monday.
    return Array.from({ length: 7 }, (_, i) =>
        format.format(new Date(Date.UTC(2026, 0, 5 + i))),
    );
}

export type OpenStatus = { open: boolean; label: string } | null;

/**
 * "Open now · until 7:30 pm", "Opens again at 3:00 pm", "Opens tomorrow at
 * 9:00 am" or "Opens Thursday at 10:00 am". Null when there are no hours.
 */
export function openStatus(
    hours: WeekHours,
    at: Date,
    zone: string,
): OpenStatus {
    const { weekday, hour } = localDay(at, zone);
    const today = hours[weekday] ?? [];
    const now = today.find(
        ([from, to]) => clockHours(from) <= hour && hour < clockHours(to),
    );

    if (now) {
        return {
            open: true,
            label: `Open now · until ${clockLabel(now[1])}`,
        };
    }

    const later = today.find(([from]) => clockHours(from) > hour);

    if (later) {
        return {
            open: false,
            label: `Closed now · opens at ${clockLabel(later[0])}`,
        };
    }

    const names = new Intl.DateTimeFormat(displayLocale(), {
        weekday: 'long',
        timeZone: 'UTC',
    });

    for (let ahead = 1; ahead <= 7; ahead++) {
        const day = ((weekday - 1 + ahead) % 7) + 1;
        const first = hours[day]?.[0];

        if (first) {
            const when =
                ahead === 1
                    ? 'tomorrow'
                    : names.format(new Date(Date.UTC(2026, 0, 4 + day)));

            return {
                open: false,
                label: `Closed now · opens ${when} at ${clockLabel(first[0])}`,
            };
        }
    }

    return null;
}
