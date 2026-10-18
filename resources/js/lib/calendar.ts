import type { CalendarBookingStatus } from '@/types/calendar';

/**
 * Pieces shared by the calendar's day, week and list views.
 */

export const statusLabel: Record<CalendarBookingStatus, string> = {
    held: 'Held',
    confirmed: 'Confirmed',
    completed: 'Completed',
    no_show: 'No-show',
    cancelled: 'Cancelled',
};

/** The left edge of a booking block says where it stands. */
export const statusEdge: Record<CalendarBookingStatus, string> = {
    confirmed: 'border-sun-deep',
    held: 'border-2 border-dashed border-sun-deep/70',
    completed: 'border-success',
    no_show: 'border-destructive opacity-70',
    cancelled: 'border-muted-foreground/40 opacity-60',
};

/** Overlapping ranges joined: [[9,12],[11,14],[15,16]] → [[9,14],[15,16]]. */
export function mergeRanges(ranges: [number, number][]): [number, number][] {
    const sorted = [...ranges].sort((a, b) => a[0] - b[0]);
    const merged: [number, number][] = [];

    for (const [from, to] of sorted) {
        const last = merged[merged.length - 1];

        if (last && from <= last[1]) {
            last[1] = Math.max(last[1], to);
        } else {
            merged.push([from, to]);
        }
    }

    return merged;
}

export type Laned<T> = { item: T; lane: number; lanes: number };

/**
 * Side-by-side lanes for things that overlap in time. Each item takes the
 * first lane free at its start; everything in a run of overlaps shares the
 * same lane count, so their widths line up.
 */
export function inLanes<T>(
    items: T[],
    span: (item: T) => [number, number],
): Laned<T>[] {
    const sorted = [...items].sort(
        (a, b) => span(a)[0] - span(b)[0] || span(b)[1] - span(a)[1],
    );
    const placed: Laned<T>[] = [];
    let run: Laned<T>[] = [];
    let laneEnds: number[] = [];
    let runEnd = -Infinity;

    const closeRun = () => {
        run.forEach((entry) => (entry.lanes = laneEnds.length));
        run = [];
        laneEnds = [];
        runEnd = -Infinity;
    };

    for (const item of sorted) {
        const [start, end] = span(item);

        if (start >= runEnd) {
            closeRun();
        }

        let lane = laneEnds.findIndex((laneEnd) => laneEnd <= start);

        if (lane === -1) {
            lane = laneEnds.length;
            laneEnds.push(end);
        } else {
            laneEnds[lane] = end;
        }

        const entry = { item, lane, lanes: 1 };
        run.push(entry);
        placed.push(entry);
        runEnd = Math.max(runEnd, end);
    }

    closeRun();

    return placed;
}
