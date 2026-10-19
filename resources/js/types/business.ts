/** The business the dashboard is showing, shared on every page. */
export type CurrentBusiness = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
    /** A demo business, rebuilt every night. */
    is_demo: boolean;
    description: string | null;
    address: string | null;
    phone: string | null;
    website: string | null;
    logo_url: string | null;
    role: 'owner' | 'staff' | null;
};
