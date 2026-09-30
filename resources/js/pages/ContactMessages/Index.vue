<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Inbox, MessageSquareReply, PenLine } from '@lucide/vue';
import ContactStatusBadge from '@/components/admin/ContactStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import TripPager from '@/components/TripPager.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/dates';
import { contact } from '@/routes';
import { show } from '@/routes/contact/messages';
import type { Paginated } from '@/types/admin';
import type { MyContactMessageListItem } from '@/types/contact';

defineProps<{
    messages: Paginated<MyContactMessageListItem>;
}>();
</script>

<template>
    <Head title="My messages" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <PageHeader
                title="My messages"
                description="Messages you sent to the TripPilot team and our replies."
                :icon="Inbox"
            />
            <Button as-child class="shrink-0">
                <Link :href="contact()">
                    <PenLine class="size-4" />
                    New message
                </Link>
            </Button>
        </div>

        <EmptyState
            v-if="messages.data.length === 0"
            :icon="Inbox"
            title="No messages yet"
            description="Questions or feedback? Send us a message from the contact page and it will show up here."
        />

        <ul v-else class="grid gap-3">
            <li v-for="item in messages.data" :key="item.id">
                <Link
                    :href="show(item.id)"
                    class="card-vibrant group flex flex-col gap-2 rounded-2xl border p-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <ContactStatusBadge
                            :status="item.status"
                            :label="item.status_label"
                        />
                        <span class="text-xs text-muted-foreground">
                            Sent {{ formatRelativeTime(item.created_at) }}
                        </span>
                    </div>
                    <p class="font-semibold group-hover:text-primary">
                        {{ item.subject }}
                    </p>
                    <p class="line-clamp-2 text-sm text-muted-foreground">
                        {{ item.excerpt }}
                    </p>
                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-xs text-muted-foreground"
                    >
                        <span>{{ item.topic_label }}</span>
                        <span
                            v-if="item.replies_count"
                            class="inline-flex items-center gap-1 font-medium text-primary"
                        >
                            <MessageSquareReply class="size-3.5" />
                            {{ item.replies_count }}
                            {{ item.replies_count === 1 ? 'reply' : 'replies' }}
                        </span>
                        <span class="ml-auto font-mono">{{
                            item.reference
                        }}</span>
                    </div>
                </Link>
            </li>
        </ul>

        <TripPager :paginated="messages" :only="['messages']" />
    </div>
</template>
