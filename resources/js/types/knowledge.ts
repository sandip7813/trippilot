export type KnowledgeDocumentStatus = 'draft' | 'published';

export type KnowledgeDocument = {
    id: string;
    title: string;
    slug: string;
    destinations: string[];
    content: string;
    status: KnowledgeDocumentStatus;
    status_label: string;
    chunk_count: number;
    created_at: string | null;
    updated_at: string | null;
};

export type KnowledgeDocumentOption = {
    value: string;
    label: string;
};

export function destinationsInputValue(
    destinations: string[] | undefined,
): string {
    return (destinations ?? []).join(', ');
}
