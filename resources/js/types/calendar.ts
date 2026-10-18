export type CalendarBookingStatus =
    'held' | 'confirmed' | 'completed' | 'no_show';

export type CalendarBooking = {
    id: number;
    /** For links and actions. */
    code: string;
    /** Readable, "7K2Q-9XMB-AT". */
    reference: string;
    staff_id: number | null;
    service_name: string;
    status: CalendarBookingStatus;
    source: 'online' | 'manual';
    starts_at: string;
    ends_at: string;
    /** The end plus the free time after. */
    blocked_until: string;
    price: number;
    deposit: number;
    currency: string;
    paid: { amount: number; gateway: string } | null;
    notes: string | null;
    customer: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    /** What the person looking can do with it. */
    can: { mark: boolean; cancel: boolean };
};

export type CalendarView = 'day' | 'week' | 'list';

export type CalendarStaff = {
    id: number;
    name: string;
    /** Working hours for each date shown, in minutes since midnight. */
    hours: Record<string, [number, number][]>;
};

export type CalendarTimeOff = {
    id: number;
    /** Null when the whole business is closed. */
    staff_id: number | null;
    starts_at: string;
    ends_at: string;
    reason: string | null;
};

/** What the new-booking form can offer this person. */
export type NewBookingOptions = {
    services: {
        id: number;
        name: string;
        duration: number;
        price: number;
        staff_ids: number[];
    }[];
    /** Who they can book in: anyone for an owner, themselves for staff. */
    staff: { id: number; name: string }[];
    currency: string;
};

/** Where the form was opened from: a day, and maybe a column and a time. */
export type NewBookingPreset = {
    date: string;
    staffId: number | null;
    /** "14:30", in the business's time. */
    time: string | null;
};
