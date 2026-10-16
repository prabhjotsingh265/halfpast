/**
 * Small time zone helpers built on Intl, so daylight saving is handled by
 * the browser's own time zone data instead of hard-coded offsets.
 */

/** Minutes the zone is ahead of UTC at that instant (London in summer: 60). */
export function offsetMinutes(instant: Date, timeZone: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(instant);

    const part = (type: Intl.DateTimeFormatPartTypes) =>
        Number(parts.find((p) => p.type === type)?.value ?? 0);

    const asIfUtc = Date.UTC(
        part('year'),
        part('month') - 1,
        part('day'),
        part('hour'),
        part('minute'),
        part('second'),
    );

    return Math.round((asIfUtc - instant.getTime()) / 60_000);
}

/** The instant a wall-clock time happens in a zone, e.g. 15:30 in London. */
export function zonedTime(
    year: number,
    month: number,
    day: number,
    hour: number,
    minute: number,
    timeZone: string,
): Date {
    const wall = Date.UTC(year, month - 1, day, hour, minute);

    // Two passes, so a time near a daylight-saving change lands on the right side of it.
    const first = new Date(
        wall - offsetMinutes(new Date(wall), timeZone) * 60_000,
    );

    return new Date(wall - offsetMinutes(first, timeZone) * 60_000);
}

/** "8:00 pm" for an instant, as the clock reads in that zone. */
export function timeIn(instant: Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
        .format(instant)
        .replace(/\s/g, ' ')
        .toLowerCase();
}

/** The calendar date (YYYY-MM-DD) an instant falls on in a zone. */
export function dateIn(instant: Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(instant);
}

/** The visitor's own time zone, e.g. "Asia/Kolkata". */
export function localZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC';
}
