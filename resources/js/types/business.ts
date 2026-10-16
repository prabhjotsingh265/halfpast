/** The business the dashboard is showing, shared on every page. */
export type CurrentBusiness = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
    role: 'owner' | 'staff' | null;
};
