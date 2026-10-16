/**
 * A working week as the editor sees it: seven days, Monday first, each a list
 * of stretches in minutes since midnight. The server stores the same thing as
 * rows of { weekday, starts_at: "09:00", ends_at: "13:00" }.
 */

import type { WorkingHours } from '@/types/staff';

export type Stretch = { start: number; end: number };

export type Week = Stretch[][];

export const DAY = 24 * 60;

export const WEEKDAYS = [
    { iso: 1, short: 'Mon', long: 'Monday', letter: 'M' },
    { iso: 2, short: 'Tue', long: 'Tuesday', letter: 'T' },
    { iso: 3, short: 'Wed', long: 'Wednesday', letter: 'W' },
    { iso: 4, short: 'Thu', long: 'Thursday', letter: 'T' },
    { iso: 5, short: 'Fri', long: 'Friday', letter: 'F' },
    { iso: 6, short: 'Sat', long: 'Saturday', letter: 'S' },
    { iso: 7, short: 'Sun', long: 'Sunday', letter: 'S' },
] as const;

const pad = (n: number) => String(n).padStart(2, '0');

/** "09:30" → 570. */
export function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** 570 → "09:30", and the end of the day → "24:00". */
export function fromMinutes(minutes: number): string {
    return `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
}

const clockFormat = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

/** 570 → "9:30 AM" or "09:30", whichever the browser's locale reads. */
export function clockLabel(minutes: number): string {
    if (minutes >= DAY) {
        return 'Midnight';
    }

    return clockFormat.format(
        new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60),
    );
}

export function emptyWeek(): Week {
    return WEEKDAYS.map(() => []);
}

export function weekFromHours(hours: WorkingHours[]): Week {
    const week = emptyWeek();

    for (const hour of hours) {
        week[hour.weekday - 1].push({
            start: toMinutes(hour.starts_at),
            end: toMinutes(hour.ends_at),
        });
    }

    return week.map(sortStretches);
}

export function hoursFromWeek(week: Week): WorkingHours[] {
    return week.flatMap((stretches, index) =>
        sortStretches(stretches).map((stretch) => ({
            weekday: index + 1,
            starts_at: fromMinutes(stretch.start),
            ends_at: fromMinutes(stretch.end),
        })),
    );
}

export function sortStretches(stretches: Stretch[]): Stretch[] {
    return [...stretches].sort((a, b) => a.start - b.start);
}

export function weekMinutes(week: Week): number {
    return week
        .flat()
        .reduce(
            (total, stretch) =>
                total + Math.max(0, stretch.end - stretch.start),
            0,
        );
}

/**
 * Problems the server would reject, worked out as the owner edits so they show
 * straight away. Keyed by the stretch's index within its day.
 */
export function stretchProblems(stretches: Stretch[]): Map<number, string> {
    const problems = new Map<number, string>();
    const order = stretches
        .map((stretch, index) => ({ ...stretch, index }))
        .sort((a, b) => a.start - b.start);

    let previousEnd: number | null = null;

    for (const stretch of order) {
        if (stretch.end <= stretch.start) {
            problems.set(stretch.index, 'Finishes before it starts');
        } else if (previousEnd !== null && stretch.start < previousEnd) {
            problems.set(stretch.index, 'Overlaps other hours');
        }

        previousEnd = Math.max(previousEnd ?? 0, stretch.end);
    }

    return problems;
}

function sameDay(a: Stretch[], b: Stretch[]): boolean {
    return (
        a.length === b.length &&
        a.every((s, i) => s.start === b[i].start && s.end === b[i].end)
    );
}

/**
 * A short line for lists: "Mon–Fri 9:00 AM–5:00 PM · Sat 10:00 AM–2:00 PM".
 * Days in a row with the same hours are grouped.
 */
export function weekSummary(week: Week): string {
    const groups: { from: number; to: number; stretches: Stretch[] }[] = [];

    week.forEach((stretches, day) => {
        if (stretches.length === 0) {
            return;
        }

        const last = groups[groups.length - 1];

        if (last && last.to === day - 1 && sameDay(last.stretches, stretches)) {
            last.to = day;
        } else {
            groups.push({ from: day, to: day, stretches });
        }
    });

    if (groups.length === 0) {
        return 'No hours set';
    }

    return groups
        .map(({ from, to, stretches }) => {
            const days =
                from === to
                    ? WEEKDAYS[from].short
                    : `${WEEKDAYS[from].short}–${WEEKDAYS[to].short}`;
            const times = stretches
                .map((s) => `${clockLabel(s.start)}–${clockLabel(s.end)}`)
                .join(', ');

            return `${days} ${times}`;
        })
        .join(' · ');
}

/** Quick starts for the editor. */
export const PRESETS: { label: string; week: () => Week }[] = [
    {
        label: 'Weekdays, 9 to 5',
        week: () =>
            WEEKDAYS.map((day) =>
                day.iso <= 5 ? [{ start: 9 * 60, end: 17 * 60 }] : [],
            ),
    },
    {
        label: 'Mon to Sat, 10 to 6',
        week: () =>
            WEEKDAYS.map((day) =>
                day.iso <= 6 ? [{ start: 10 * 60, end: 18 * 60 }] : [],
            ),
    },
    {
        label: 'Weekdays with lunch',
        week: () =>
            WEEKDAYS.map((day) =>
                day.iso <= 5
                    ? [
                          { start: 9 * 60, end: 13 * 60 },
                          { start: 14 * 60, end: 18 * 60 },
                      ]
                    : [],
            ),
    },
];
