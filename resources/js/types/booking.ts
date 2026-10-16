export type BookableService = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    /** Minor units. */
    price: number;
    /** Minor units; 0 when no deposit is taken. */
    deposit: number;
    staff_ids: number[];
};

export type BookableStaff = {
    id: number;
    name: string;
    bio: string | null;
};

export type BookingSelection = {
    service_id: number | null;
    staff_id: number | null;
    /** First date in the strip. */
    from: string;
    date: string | null;
};

export type BookingDay = {
    date: string;
    /** How many free times it has. */
    free: number;
};

export type BookableTime = {
    /** UTC instant, "2026-11-03T10:30:00Z". */
    starts_at: string;
    staff_ids: number[];
};
