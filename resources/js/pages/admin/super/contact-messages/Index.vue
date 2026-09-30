<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Inbox, MessageSquareReply, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import ContactStatusBadge from '@/components/admin/ContactStatusBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import TripPager from '@/components/TripPager.vue';
import { Input } from '@/components/ui/input';
import { formatRelativeTime } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/super/contact-messages';
import type {
    AdminContactMessageListItem,
    ContactMessageStatus,
    Paginated,
} from '@/types/admin';

const props = defineProps<{
    messages: Paginated<AdminContactMessageListItem>;
    filters: { status: ContactMessageStatus | null; search: string };
    counts: Record<'all' | ContactMessageStatus, number>;
}>();

const tabs: { value: ContactMessageStatus | null; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'new', label: 'New' },
    { value: 'replied', label: 'Replied' },
    { value: 'closed', label: 'Closed' },
];

const search = ref(props.filters.search);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function visit(status: ContactMessageStatus | null, term: string): void {
    router.get(
        index.url({
            query: {
                ...(status ? { status } : {}),
                ...(term ? { search: term } : {}),
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, (term) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(
        () => visit(props.filters.status, term.trim()),
        350,
    );
});

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
</script>

<template>
    <Head title="Contact messages" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Contact messages"
            description="Messages sent through the public contact page. Replies are emailed to the sender."
            :icon="Inbox"
        />

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div
                class="inline-flex w-fit rounded-full border border-border bg-muted/50 p-1"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.label"
                    type="button"
                    :class="
                        cn(
                            'rounded-full px-4 py-1.5 text-sm font-medium transition',
                            filters.status === tab.value
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="visit(tab.value, search.trim())"
                >
                    {{ tab.label }}
                    <span
                        class="ml-1 text-xs text-muted-foreground tabular-nums"
                    >
                        {{ counts[tab.value ?? 'all'] }}
                    </span>
                </button>
            </div>

            <div class="relative w-full sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search name, email or subject"
                    class="pl-9"
                    aria-label="Search contact messages"
                />
            </div>
        </div>

        <EmptyState
            v-if="messages.data.length === 0"
            :icon="Inbox"
            :title="
                filters.search || filters.status
                    ? 'No matching messages'
                    : 'No messages yet'
            "
            description="Messages people send from the contact page will show up here."
        />

        <ul v-else class="grid gap-3">
            <li v-for="item in messages.data" :key="item.id">
                <Link
                    :href="show(item.id)"
                    :class="
                        cn(
                            'card-vibrant group flex gap-4 rounded-2xl border p-4 sm:p-5',
                            item.status === 'new' &&
                                'border-l-4 border-l-primary',
                        )
                    "
                >
                    <span
                        class="brand-gradient flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                    >
                        {{ initials(item.name) }}
                    </span>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                        >
                            <p class="truncate text-sm">
                                <span
                                    :class="
                                        item.status === 'new'
                                            ? 'font-bold'
                                            : 'font-semibold'
                                    "
                                    >{{ item.name }}</span
                                >
                                <span class="text-muted-foreground">
                                    · {{ item.email }}</span
                                >
                            </p>
                            <span class="text-xs text-muted-foreground">
                                {{ formatRelativeTime(item.created_at) }}
                            </span>
                        </div>
                        <p
                            class="truncate font-semibold group-hover:text-primary"
                        >
                            {{ item.subject }}
                        </p>
                        <p class="line-clamp-2 text-sm text-muted-foreground">
                            {{ item.excerpt }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <ContactStatusBadge
                                :status="item.status"
                                :label="item.status_label"
                            />
                            <span
                                class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ item.topic_label }}
                            </span>
                            <span
                                v-if="item.replies_count"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <MessageSquareReply class="size-3.5" />
                                {{ item.replies_count }}
                                {{
                                    item.replies_count === 1
                                        ? 'reply'
                                        : 'replies'
                                }}
                            </span>
                            <span
                                class="ml-auto font-mono text-xs text-muted-foreground"
                            >
                                {{ item.reference }}
                            </span>
                        </div>
                    </div>
                </Link>
            </li>
        </ul>

        <TripPager :paginated="messages" :only="['messages']" />
    </div>
</template>
