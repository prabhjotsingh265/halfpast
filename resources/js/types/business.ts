/** The business the dashboard is showing, shared on every page. */
export type CurrentBusiness = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
    /** A demo business, rebuilt every night. */
    is_demo: boolean;
    role: 'owner' | 'staff' | null;
};
