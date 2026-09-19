<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bell, CalendarClock, CheckCheck, ChevronRight, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatRelativeTime } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { index as notificationsIndex, read, readAll } from '@/routes/notifications';
import type { Paginated } from '@/types/admin';

type AppNotification = {
    id: string;
    kind: 'reminder' | 'shared';
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string | null;
};

const props = defineProps<{
    items: Paginated<AppNotification>;
    filter: 'all' | 'unread';
    counts: { all: number; unread: number };
}>();

const unreadCount = computed(() => props.counts.unread);

const groupLabel = (iso: string | null): string => {
    if (!iso) {
        return 'Earlier';
    }

    const date = new Date(iso);
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);
    const days = Math.floor((startOfToday.getTime() - date.getTime()) / 86400000);

    if (date >= startOfToday) {
        return 'Today';
    }

    if (days < 1) {
        return 'Yesterday';
    }

    return days < 7 ? 'This week' : 'Earlier';
};

const groups = computed(() => {
    const map = new Map<string, AppNotification[]>();

    for (const notification of props.items.data) {
        const label = groupLabel(notification.created_at);
        map.set(label, [...(map.get(label) ?? []), notification]);
    }

    return [...map.entries()];
});

const kindMeta = {
    reminder: { icon: CalendarClock, label: 'Reminder' },
    shared: { icon: UserPlus, label: 'Shared trip' },
};

const tabs = computed(() => [
    { key: 'all' as const, label: 'All', count: props.counts.all },
    { key: 'unread' as const, label: 'Unread', count: props.counts.unread },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Notifications',
                href: notificationsIndex(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Notifications" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                title="Notifications"
                description="Trip reminders and updates."
                :icon="Bell"
            />
            <Button v-if="unreadCount > 0" variant="outline" size="sm" as-child>
                <Link :href="readAll()" method="patch" as="button">
                    <CheckCheck class="size-4" />
                    Mark all as read
                </Link>
            </Button>
        </div>

        <div
            v-if="counts.all > 0"
            class="inline-flex w-fit gap-1 rounded-lg bg-muted p-1"
            role="tablist"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="notificationsIndex({ query: tab.key === 'all' ? {} : { filter: tab.key } })"
                role="tab"
                :aria-selected="filter === tab.key"
                :class="
                    cn(
                        'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        filter === tab.key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
            >
                {{ tab.label }}
                <span
                    class="rounded-full bg-primary/10 px-1.5 text-xs text-primary"
                >
                    {{ tab.count }}
                </span>
            </Link>
        </div>

        <EmptyState
            v-if="counts.all === 0"
            title="You're all caught up"
            description="Reminders for upcoming trips will show up here."
            :icon="Bell"
        />

        <p
            v-else-if="items.data.length === 0"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            No unread notifications.
        </p>

        <section v-for="[label, group] in groups" :key="label" class="space-y-2">
            <h2
                class="px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ label }}
            </h2>

            <Card class="gap-0 divide-y divide-border/60 overflow-hidden py-0">
                <div
                    v-for="notification in group"
                    :key="notification.id"
                    :class="
                        cn(
                            'flex items-start gap-4 p-4 transition-colors hover:bg-muted/40',
                            !notification.read && 'bg-primary/5',
                        )
                    "
                >
                    <span
                        :class="
                            cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-full',
                                notification.read
                                    ? 'bg-muted text-muted-foreground'
                                    : 'brand-gradient text-white shadow-md shadow-teal-500/20',
                            )
                        "
                    >
                        <component :is="kindMeta[notification.kind].icon" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                :class="
                                    cn(
                                        'text-sm',
                                        notification.read
                                            ? 'text-muted-foreground'
                                            : 'font-semibold',
                                    )
                                "
                            >
                                {{ notification.title }}
                            </p>
                            <span
                                v-if="!notification.read"
                                class="size-2 rounded-full bg-primary"
                            />
                            <span
                                class="rounded-full border border-border/60 px-2 py-0.5 text-[11px] text-muted-foreground"
                            >
                                {{ kindMeta[notification.kind].label }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ notification.message }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <span class="text-xs text-muted-foreground/80">
                                {{ formatRelativeTime(notification.created_at) }}
                            </span>
                            <Link
                                v-if="notification.url"
                                :href="read(notification.id)"
                                method="patch"
                                as="button"
                                class="inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:underline"
                            >
                                View trip
                                <ChevronRight class="size-3.5" />
                            </Link>
                            <Link
                                v-if="!notification.read"
                                :href="read(notification.id, { query: { stay: 1 } })"
                                method="patch"
                                as="button"
                                preserve-scroll
                                class="text-xs font-medium text-muted-foreground hover:text-foreground"
                            >
                                Mark as read
                            </Link>
                        </div>
                    </div>
                </div>
            </Card>
        </section>

        <div
            v-if="items.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ items.from ?? 0 }}–{{ items.to ?? 0 }} of
                {{ items.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template
                    v-for="link in items.links"
                    :key="`${link.label}-${link.url}`"
                >
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link :href="link.url">
                            <span v-html="link.label" />
                        </Link>
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled>
                        <span v-html="link.label" />
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
