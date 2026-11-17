export type LegalDetails = {
    business_name: string;
    support_email: string | null;
    support_phone: string | null;
    address: string | null;
    jurisdiction: string;
};

/** A link to a published content page, shared with every page. */
export type FooterPageLink = {
    title: string;
    href: string;
};

/** A content page as shown to visitors; `html` is already sanitised. */
export type PublicContentPage = {
    slug: string;
    title: string;
    description: string | null;
    html: string;
    is_published: boolean;
    updated_at: string | null;
};

export type AdminContentPage = {
    id: number;
    slug: string;
    title: string;
    url: string;
    is_system: boolean;
    is_published: boolean;
    show_in_footer: boolean;
    sort_order: number;
    updated_at: string | null;
    updated_by?: string | null;
    description?: string | null;
    body?: string;
};

export type ContentPlaceholder = {
    token: string;
    value: string;
};
