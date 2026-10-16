/** A stretch of time off, with its dates and times as the business's clock reads them. */
export type TimeOffEntry = {
    id: number;
    /** Null when the whole business is closed. */
    staff_id: number | null;
    staff_name: string | null;
    reason: string | null;
    all_day: boolean;
    /** "2026-12-24" */
    starts_on: string;
    /** The last day, included. */
    ends_on: string;
    /** "14:00", or null for whole days. */
    starts_time: string | null;
    ends_time: string | null;
    /** Already under way. */
    started: boolean;
};

export type CalendarMark = {
    tone: 'closed' | 'staff';
    label: string;
};
