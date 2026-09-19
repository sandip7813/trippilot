<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, CalendarClock, ChevronRight, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatRelativeTime } from '@/lib/dates';
import { cn } from '@/lib/utils';
import { index as notificationsIndex, read, readAll } from '@/routes/notifications';

const page = usePage();

const unread = computed(() => page.props.notifications.unread);
const recent = computed(() => page.props.notifications.recent);

const kindIcons = {
    reminder: CalendarClock,
    shared: UserPlus,
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative ml-auto rounded-full p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Notifications"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    class="brand-gradient absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white ring-2 ring-background"
                >
                    {{ unread > 99 ? '99+' : unread }}
                </span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-[22rem] overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold">Notifications</h2>
                    <span
                        v-if="unread > 0"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        {{ unread }} new
                    </span>
                </div>
                <Link
                    v-if="unread > 0"
                    :href="readAll()"
                    method="patch"
                    as="button"
                    class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    Mark all as read
                </Link>
            </div>

            <div
                v-if="recent.length === 0"
                class="flex flex-col items-center gap-2 px-6 py-10 text-center"
            >
                <div class="flex size-10 items-center justify-center rounded-full bg-muted">
                    <Bell class="size-5 text-muted-foreground" />
                </div>
                <p class="text-sm font-medium">You're all caught up</p>
                <p class="text-xs text-muted-foreground">
                    Trip reminders and updates will show up here.
                </p>
            </div>

            <div v-else class="max-h-96 divide-y divide-border/50 overflow-y-auto">
                <DropdownMenuItem
                    v-for="notification in recent"
                    :key="notification.id"
                    as-child
                    class="rounded-none p-0 focus:bg-muted/60"
                >
                    <Link
                        :href="read(notification.id)"
                        method="patch"
                        as="button"
                        :class="
                            cn(
                                'flex w-full cursor-pointer items-start gap-3 px-4 py-3 text-left',
                                !notification.read && 'bg-primary/5',
                            )
                        "
                    >
                        <span
                            :class="
                                cn(
                                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                                    notification.read
                                        ? 'bg-muted text-muted-foreground'
                                        : 'brand-gradient text-white shadow-sm shadow-teal-500/20',
                                )
                            "
                        >
                            <component
                                :is="kindIcons[notification.kind] ?? Bell"
                                class="size-4"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-2">
                                <span
                                    :class="
                                        cn(
                                            'line-clamp-1 text-sm',
                                            notification.read
                                                ? 'font-normal text-muted-foreground'
                                                : 'font-semibold',
                                        )
                                    "
                                >
                                    {{ notification.title }}
                                </span>
                                <span
                                    v-if="!notification.read"
                                    class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                />
                            </span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">
                                {{ notification.message }}
                            </span>
                            <span class="mt-1 block text-[11px] text-muted-foreground/80">
                                {{ formatRelativeTime(notification.created_at) }}
                            </span>
                        </span>
                    </Link>
                </DropdownMenuItem>
            </div>

            <DropdownMenuItem
                as-child
                class="rounded-none border-t border-border/60 p-0 focus:bg-muted/60"
            >
                <Link
                    :href="notificationsIndex()"
                    class="flex w-full cursor-pointer items-center justify-center gap-1 px-4 py-3 text-sm font-medium text-primary"
                >
                    View all notifications
                    <ChevronRight class="size-4" />
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
