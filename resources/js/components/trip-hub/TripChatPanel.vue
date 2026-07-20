<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { MessageCircle, Send, Sparkles } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import TripController from '@/actions/App/Http/Controllers/TripController';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { Trip, TripChatMessage } from '@/types/trip';

const props = defineProps<{
    trip: Trip;
    aiConfigured: boolean;
    variant?: 'vacation' | 'road';
}>();

const page = usePage();
const messageInput = ref('');
const messagesContainer = ref<HTMLElement | null>(null);

const messages = computed(() => props.trip.chat_messages ?? []);
const canChat = computed(() => props.aiConfigured);
const isRoadTrip = computed(() => props.variant === 'road');

const chatHint = computed((): string => {
    if (!props.aiConfigured) {
        return 'Add GEMINI_API_KEY to your environment to chat with TripPilot.';
    }

    if (isRoadTrip.value) {
        return 'Ask about pacing, stops, packing, tolls, or notes. TripPilot can update trip notes and any day-by-day plan when you ask.';
    }

    return 'Ask questions or request itinerary tweaks. TripPilot can update days, notes, packing, and budget when you ask.';
});

const messagePlaceholder = computed((): string => {
    if (isRoadTrip.value) {
        return 'Ask about drive pacing, toll alternatives, packing for the road, or update your trip notes...';
    }

    return 'Ask TripPilot to adjust Day 2, suggest restaurants, or refine your packing list...';
});

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

function messageClasses(message: TripChatMessage): string {
    return cn(
        'max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm',
        message.role === 'user'
            ? 'ml-auto bg-primary text-primary-foreground'
            : 'mr-auto border border-border/60 bg-muted/30 text-foreground',
    );
}
</script>

<template>
    <Card class="card-vibrant overflow-hidden">
        <div class="brand-gradient h-1.5 opacity-90" />
        <CardHeader
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <CardTitle class="text-lg font-bold">Trip assistant</CardTitle>
                    <Badge
                        class="bg-violet-500/15 text-violet-700 dark:text-violet-300"
                    >
                        <Sparkles class="mr-1 size-3" />
                        AI chat
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ chatHint }}
                </p>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <InputError
                :message="(page.props.errors as Record<string, string>).chat"
            />
            <InputError
                :message="(page.props.errors as Record<string, string>).ai"
            />

            <div
                ref="messagesContainer"
                class="max-h-96 space-y-3 overflow-y-auto rounded-lg border border-border/60 bg-background/60 p-4"
            >
                <div
                    v-if="messages.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-10 text-center text-sm text-muted-foreground"
                >
                    <MessageCircle class="size-8 opacity-50" />
                    <p>
                        {{
                            isRoadTrip
                                ? 'Ask about your drive — rest stops, EV range, weather prep, or what to pack.'
                                : 'Start a conversation about this trip — packing tips, day swaps, or local recommendations.'
                        }}
                    </p>
                </div>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex flex-col gap-1"
                    :class="message.role === 'user' ? 'items-end' : 'items-start'"
                >
                    <div :class="messageClasses(message)">
                        <p class="whitespace-pre-wrap">{{ message.content }}</p>
                    </div>
                    <div
                        class="flex items-center gap-2 px-1 text-[11px] text-muted-foreground"
                    >
                        <span>{{
                            message.role === 'user' ? 'You' : 'TripPilot'
                        }}</span>
                        <Badge
                            v-if="message.patch_applied"
                            variant="outline"
                            class="text-[10px]"
                        >
                            Trip updated
                        </Badge>
                    </div>
                </div>
            </div>

            <Form
                v-bind="TripController.chat.form(trip.id)"
                v-slot="{ processing, errors }"
                class="space-y-3"
                :options="{ preserveScroll: true }"
                @success="messageInput = ''"
            >
                <FormSavingOverlay
                    :show="processing"
                    message="TripPilot is thinking..."
                />
                <label class="sr-only" for="trip-chat-message">Message</label>
                <textarea
                    id="trip-chat-message"
                    v-model="messageInput"
                    name="message"
                    rows="3"
                    maxlength="2000"
                    :disabled="!canChat || processing"
                    :placeholder="messagePlaceholder"
                    class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <InputError :message="errors.message" />
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        {{ messageInput.length }}/2000
                    </p>
                    <Button
                        type="submit"
                        :disabled="!canChat || processing || messageInput.trim() === ''"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        <Send v-else class="mr-2 size-4" />
                        Send
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
