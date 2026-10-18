import { displayLocale } from '@/lib/hydration';

/**
 * Calendar dates as "YYYY-MM-DD" strings. The maths runs on UTC midnights,
 * so the browser's own time zone (and its clock changes) can never move a
 * date by a day. Strings in this shape also sort and compare correctly.
 */

const pad = (n: number) => String(n).padStart(2, '0');

export function toDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
}

export function fromDate(date: Date): string {
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
}

export function addDays(date: string, days: number): string {
    const next = toDate(date);

    next.setUTCDate(next.getUTCDate() + days);

    return fromDate(next);
}

/** The first of the month `months` after the one `date` is in. */
export function addMonths(date: string, months: number): string {
    const d = toDate(date);

    return fromDate(
        new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + months, 1)),
    );
}

export function startOfMonth(date: string): string {
    return `${date.slice(0, 7)}-01`;
}

/** Days from one date to another: 2026-12-24 → 2026-12-26 is 2. */
export function daysBetween(from: string, to: string): number {
    return Math.round(
        (toDate(to).getTime() - toDate(from).getTime()) / 86_400_000,
    );
}

/** ISO weekday, 1 for Monday to 7 for Sunday. */
export function weekday(date: string): number {
    return toDate(date).getUTCDay() || 7;
}

/**
 * A month laid out in weeks starting on Monday, with nulls for the days
 * before the 1st and after the last.
 */
export function monthWeeks(month: string): (string | null)[][] {
    const first = startOfMonth(month);
    const length = daysBetween(first, addMonths(first, 1));
    const cells: (string | null)[] = [
        ...Array.from({ length: weekday(first) - 1 }, () => null),
        ...Array.from({ length }, (_, i) => addDays(first, i)),
    ];

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    return Array.from({ length: cells.length / 7 }, (_, i) =>
        cells.slice(i * 7, i * 7 + 7),
    );
}

export function formatDate(
    date: string,
    options: Intl.DateTimeFormatOptions = {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    },
): string {
    return new Intl.DateTimeFormat(displayLocale(), {
        ...options,
        timeZone: 'UTC',
    }).format(toDate(date));
}

export function monthLabel(month: string): string {
    return formatDate(month, { month: 'long', year: 'numeric' });
}

/** Every date from one to another, both included. */
export function eachDate(from: string, to: string): string[] {
    const days = Math.max(0, daysBetween(from, to));

    return Array.from({ length: days + 1 }, (_, i) => addDays(from, i));
}
