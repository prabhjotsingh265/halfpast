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

/** A held (or just confirmed) booking, as the checkout page sees it. */
export type CheckoutBooking = {
    code: string;
    service_name: string;
    service_id: number | null;
    staff_name: string | null;
    starts_at: string;
    ends_at: string;
    price: number;
    deposit: number;
    currency: string;
    /** Still held, and the hold hasn't run out. */
    held: boolean;
    /** The hold has run out but nobody has taken the time, so it can still be booked. */
    renewable: boolean;
    confirmed: boolean;
    hold_expires_at: string | null;
};

export type CheckoutCustomer = {
    name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
};

/** A booking as the customer's own page shows it. */
export type ManagedBooking = {
    /** Readable reference, "7K2Q-9XMB-AT". */
    code: string;
    status:
        | 'held'
        | 'confirmed'
        | 'expired'
        | 'cancelled'
        | 'completed'
        | 'no_show';
    service_name: string;
    service_id: number | null;
    staff_name: string | null;
    starts_at: string;
    ends_at: string;
    price: number;
    deposit: number;
    currency: string;
    customer_name: string | null;
    customer_email: string | null;
    notes: string | null;
    /** Signed link to the .ics file, once confirmed. */
    calendar_url: string | null;
    cancelled_by: 'customer' | 'business' | 'system' | null;
    /** Minor units going back to the customer after a cancellation. */
    refund_due: number;
    /** The gateway has done the refund. */
    refunded: boolean;
};

/** What cancelling now would mean, and where to send it. */
export type CancellationOffer = {
    refund: number;
    kept: number;
    free_until: string;
    window_hours: number;
    /** Signed POST address. */
    url: string;
};

/** The latest payment attempt for a booking, as the checkout shows it. */
export type CheckoutPayment = {
    status:
        | 'pending'
        | 'authorized'
        | 'captured'
        | 'released'
        | 'refunded'
        | 'partially_refunded'
        | 'failed';
    outcome:
        | 'confirmed'
        | 'slot_taken'
        | 'duplicate'
        | 'amount_mismatch'
        | 'not_bookable'
        | 'capture_failed'
        | null;
    failure_reason: string | null;
    refunded: boolean;
};
