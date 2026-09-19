export type User = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    mobile_number: string | null;
    role: 'user' | 'admin' | 'super_admin';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */
