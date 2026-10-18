export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    /** A one-click demo login, whose sign-in details can't be changed. */
    is_demo?: boolean;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
