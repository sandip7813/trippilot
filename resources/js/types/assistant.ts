export type AssistantRagSource = {
    document_id: string;
    title: string;
    score?: number | null;
};

export type AssistantMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    rag_sources?: AssistantRagSource[];
};

export type AssistantConversationSummary = {
    id: string;
    title: string;
    updated_at: string | null;
};

export type AssistantConversation = {
    id: string;
    title: string;
    messages: AssistantMessage[];
    updated_at: string | null;
};
