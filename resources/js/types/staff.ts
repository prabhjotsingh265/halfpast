export type WorkingHours = {
    /** ISO weekday: 1 is Monday, 7 is Sunday. */
    weekday: number;
    /** "09:00" */
    starts_at: string;
    /** "17:00", or "24:00" for midnight. */
    ends_at: string;
};

export type StaffMember = {
    id: number;
    name: string;
    bio: string | null;
    /** A square photo, or null to show initials. */
    photo_url: string | null;
    is_active: boolean;
    service_ids: number[];
    hours: WorkingHours[];
    /** Service names, only on the list page. */
    services?: string[];
};

export type ServiceOption = {
    id: number;
    name: string;
    duration_minutes: number;
    is_active: boolean;
};

export type StaffOption = {
    id: number;
    name: string;
    photo_url?: string | null;
    is_active: boolean;
};
