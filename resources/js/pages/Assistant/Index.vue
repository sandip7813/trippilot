<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { MessageCircle, Plus, Send, Sparkles, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import AssistantConversationController from '@/actions/App/Http/Controllers/Assistant/AssistantConversationController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index as assistantIndex } from '@/routes/assistant';
import type {
    AssistantConversation,
    AssistantConversationSummary,
    AssistantMessage,
} from '@/types/assistant';

const props = defineProps<{
    conversations: AssistantConversationSummary[];
    activeConversation: AssistantConversation | null;
    aiConfigured: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Travel assistant',
                href: assistantIndex(),
            },
        ],
    },
});

const messagesContainer = ref<HTMLElement | null>(null);
const messageInput = ref('');

const messages = computed(() => props.activeConversation?.messages ?? []);
const canChat = computed(() => props.aiConfigured && props.activeConversation !== null);

watch(
    messages,
    async () => {
        await nextTick();
        scrollToLatest();
    },
    { deep: true },
);

function scrollToLatest(): void {
    const container = messagesContainer.value;

    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function messageClasses(message: AssistantMessage): string {
    return cn(
        'max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm whitespace-pre-wrap',
        message.role === 'user'
            ? 'ml-auto bg-primary text-primary-foreground'
            : 'mr-auto border border-border/60 bg-muted/30 text-foreground',
    );
}

function startNewConversation(): void {
    router.post(AssistantConversationController.store.url());
}

function deleteConversation(conversationId: string): void {
    if (!confirm('Delete this conversation?')) {
        return;
    }

    router.delete(
        AssistantConversationController.destroy.url(conversationId),
    );
}
</script>

<template>
    <Head title="Travel assistant" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Travel assistant"
            description="Ask anything about destinations, seasons, budgets, packing, and trip ideas."
            :icon="MessageCircle"
        >
            <template #actions>
                <Button type="button" @click="startNewConversation">
                    <Plus class="size-4" />
                    New chat
                </Button>
            </template>
        </PageHeader>

        <div class="grid min-h-[32rem] flex-1 gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <Card class="border-sidebar-border/70 dark:border-sidebar-border">
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-semibold">History</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 p-2 pt-0">
                    <p
                        v-if="conversations.length === 0"
                        class="px-2 py-4 text-xs text-muted-foreground"
                    >
                        No conversations yet. Start a new chat to ask TripPilot
                        anything travel-related.
                    </p>
                    <div
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        class="group flex items-center gap-1"
                    >
                        <Link
                            :href="
                                AssistantConversationController.show.url(
                                    conversation.id,
                                )
                            "
                            class="flex min-w-0 flex-1 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-muted"
                            :class="
                                activeConversation?.id === conversation.id
                                    ? 'bg-primary/10 font-medium text-primary'
                                    : 'text-foreground'
                            "
                        >
                            <span class="truncate">{{ conversation.title }}</span>
                        </Link>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-8 shrink-0 opacity-0 group-hover:opacity-100"
                            @click="deleteConversation(conversation.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card class="card-vibrant flex min-h-[32rem] flex-col overflow-hidden">
                <div class="brand-gradient h-1.5 opacity-90" />
                <CardHeader class="border-b border-border/60 pb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle class="text-lg font-bold">
                            {{
                                activeConversation?.title ?? 'Start a conversation'
                            }}
                        </CardTitle>
                        <Badge
                            class="bg-violet-500/15 text-violet-700 dark:text-violet-300"
                        >
                            <Sparkles class="mr-1 size-3" />
                            Open chat
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            aiConfigured
                                ? 'General travel Q&A with knowledge-base context when available.'
                                : 'Add GEMINI_API_KEY to your environment to use the assistant.'
                        }}
                    </p>
                </CardHeader>

                <CardContent class="flex flex-1 flex-col gap-4 p-0">
                    <div
                        v-if="!activeConversation"
                        class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center"
                    >
                        <MessageCircle class="size-12 text-muted-foreground/60" />
                        <div class="space-y-2">
                            <p class="font-medium">Ask TripPilot anything</p>
                            <p class="max-w-md text-sm text-muted-foreground">
                                Compare destinations, plan weekend getaways,
                                understand monsoon travel, or get packing tips —
                                without needing a saved trip first.
                            </p>
                        </div>
                        <Button type="button" @click="startNewConversation">
                            <Plus class="size-4" />
                            Start new chat
                        </Button>
                    </div>

                    <template v-else>
                        <div
                            ref="messagesContainer"
                            class="flex-1 space-y-4 overflow-y-auto px-4 py-4 md:px-6"
                        >
                            <p
                                v-if="messages.length === 0"
                                class="text-center text-sm text-muted-foreground"
                            >
                                Ask about destinations, budgets, seasons, or
                                itinerary ideas to get started.
                            </p>

                            <article
                                v-for="message in messages"
                                :key="message.id"
                                :class="messageClasses(message)"
                            >
                                <p>{{ message.content }}</p>
                                <div
                                    v-if="
                                        message.role === 'assistant'
                                            && message.rag_sources?.length
                                    "
                                    class="mt-3 flex flex-wrap gap-2"
                                >
                                    <Badge
                                        v-for="source in message.rag_sources"
                                        :key="source.document_id"
                                        variant="outline"
                                        class="bg-background/80 text-[11px]"
                                    >
                                        {{ source.title }}
                                    </Badge>
                                </div>
                            </article>
                        </div>

                        <Form
                            v-bind="
                                AssistantConversationController.storeMessage.form(
                                    activeConversation.id,
                                )
                            "
                            reset-on-success
                            class="border-t border-border/60 p-4 md:p-6"
                            v-slot="{ errors, processing }"
                            @success="messageInput = ''"
                        >
                            <FormSavingOverlay
                                :show="processing"
                                message="TripPilot is thinking..."
                            />

                            <InputError
                                :message="errors.message"
                                class="mb-3"
                            />

                            <div class="flex gap-2">
                                <label class="sr-only" for="assistant-message"
                                    >Message</label
                                >
                                <textarea
                                    id="assistant-message"
                                    v-model="messageInput"
                                    name="message"
                                    rows="3"
                                    class="min-h-24 flex-1 resize-y rounded-xl border border-input bg-background px-4 py-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :placeholder="
                                        canChat
                                            ? 'Ask about destinations, budgets, seasons, packing, or trip ideas...'
                                            : 'AI assistant is unavailable.'
                                    "
                                    :disabled="!canChat || processing"
                                />
                                <Button
                                    type="submit"
                                    class="self-end"
                                    :disabled="!canChat || processing || !messageInput.trim()"
                                >
                                    <Spinner v-if="processing" />
                                    <Send v-else class="size-4" />
                                </Button>
                            </div>
                        </Form>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
