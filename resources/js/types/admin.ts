export type AdminUserRoleOption = {
    value: 'user' | 'admin' | 'super_admin';
    label: string;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: AdminUserRoleOption['value'];
    role_label: string;
    email_verified_at: string | null;
    created_at: string | null;
    can_update_role: boolean;
    assignable_roles: AdminUserRoleOption[];
    is_self: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type AdminTrip = {
    id: string;
    title: string;
    type: 'vacation' | 'road';
    type_label: string;
    status: 'draft' | 'planned' | 'archived';
    status_label: string;
    destination_label: string | null;
    start_date: string | null;
    end_date: string | null;
    created_at: string | null;
    owner: {
        id: number;
        name: string | null;
        email: string | null;
    };
    show_url: string;
};
