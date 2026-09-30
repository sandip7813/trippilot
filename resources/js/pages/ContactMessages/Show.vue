<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, PenLine, Send } from '@lucide/vue';
import ContactStatusBadge from '@/components/admin/ContactStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { contact } from '@/routes';
import { index } from '@/routes/contact/messages';
import type { MyContactMessage } from '@/types/contact';

defineProps<{
    contactMessage: MyContactMessage;
}>();

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="contactMessage.subject" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <Button variant="ghost" size="sm" class="-ml-2" as-child>
                <Link :href="index()">
                    <ArrowLeft class="mr-1 size-4" />
                    My messages
                </Link>
            </Button>
        </div>

        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <ContactStatusBadge
                    :status="contactMessage.status"
                    :label="contactMessage.status_label"
                />
                <span class="text-xs text-muted-foreground">
                    {{ contactMessage.topic_label }}
                </span>
                <span class="font-mono text-xs text-muted-foreground">
                    · {{ contactMessage.reference }}
                </span>
            </div>
            <h1
                class="text-2xl font-bold tracking-tight break-words md:text-3xl"
            >
                {{ contactMessage.subject }}
            </h1>
        </div>

        <Card>
            <CardContent class="space-y-3 pt-6">
                <p class="text-xs font-medium text-muted-foreground">
                    You wrote · {{ formatDateTime(contactMessage.created_at) }}
                </p>
                <p
                    class="text-[15px] leading-relaxed break-words whitespace-pre-line"
                >
                    {{ contactMessage.message }}
                </p>
            </CardContent>
        </Card>

        <div
            v-for="reply in contactMessage.replies"
            :key="reply.id"
            class="ml-4 rounded-2xl border border-primary/20 bg-primary/5 p-5 sm:ml-10"
        >
            <div class="mb-3 flex items-center gap-3">
                <span
                    class="brand-gradient flex size-9 shrink-0 items-center justify-center rounded-full text-white"
                >
                    <Send class="size-4" />
                </span>
                <div>
                    <p class="text-sm font-semibold">TripPilot team</p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(reply.created_at) }} · also sent to
                        your email
                    </p>
                </div>
            </div>
            <p class="text-sm leading-relaxed break-words whitespace-pre-line">
                {{ reply.body }}
            </p>
        </div>

        <div
            v-if="contactMessage.replies.length === 0"
            class="flex items-start gap-3 rounded-2xl border border-dashed p-5 text-sm text-muted-foreground"
        >
            <Clock class="mt-0.5 size-4 shrink-0" />
            <p>
                We've received your message and will reply by email, usually
                within 1–2 business days. Replies also appear here.
            </p>
        </div>

        <div
            class="flex flex-col items-start gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Need more help? Send a new message and mention
                <span class="font-mono font-medium text-foreground">{{
                    contactMessage.reference
                }}</span
                >.
            </p>
            <Button variant="outline" as-child>
                <Link :href="contact()">
                    <PenLine class="size-4" />
                    New message
                </Link>
            </Button>
        </div>
    </div>
</template>
