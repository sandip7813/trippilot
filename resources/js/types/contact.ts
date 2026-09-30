import type { ContactMessageStatus } from '@/types/admin';

export type MyContactMessageSummary = {
    id: number;
    reference: string;
    topic_label: string;
    subject: string;
    status: ContactMessageStatus;
    status_label: string;
    created_at: string | null;
    replied_at: string | null;
};

export type MyContactMessageListItem = MyContactMessageSummary & {
    excerpt: string;
    replies_count: number;
};

export type MyContactMessage = MyContactMessageSummary & {
    message: string;
    replies: { id: number; body: string; created_at: string | null }[];
};
