<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();

/**
 * Each menu entry gets its own colour, cycling through the palette, so the
 * sidebar reads as a set of distinct destinations rather than a grey list.
 */
const itemPalette: { icon: string; active: string }[] = [
    {
        icon: 'text-teal-600 dark:text-teal-300',
        active: 'from-teal-500 to-emerald-500 shadow-teal-500/30',
    },
    {
        icon: 'text-sky-600 dark:text-sky-300',
        active: 'from-sky-500 to-blue-500 shadow-sky-500/30',
    },
    {
        icon: 'text-violet-600 dark:text-violet-300',
        active: 'from-violet-500 to-fuchsia-500 shadow-violet-500/30',
    },
    {
        icon: 'text-amber-600 dark:text-amber-300',
        active: 'from-amber-500 to-orange-500 shadow-amber-500/30',
    },
    {
        icon: 'text-rose-600 dark:text-rose-300',
        active: 'from-rose-500 to-pink-500 shadow-rose-500/30',
    },
    {
        icon: 'text-indigo-600 dark:text-indigo-300',
        active: 'from-indigo-500 to-violet-500 shadow-indigo-500/30',
    },
    {
        icon: 'text-emerald-600 dark:text-emerald-300',
        active: 'from-emerald-500 to-teal-500 shadow-emerald-500/30',
    },
];

function paletteFor(index: number): { icon: string; active: string } {
    return itemPalette[index % itemPalette.length];
}
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel
            class="text-[11px] font-semibold tracking-widest text-sidebar-foreground/55 uppercase"
        >
            {{ label ?? 'Platform' }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="(item, index) in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    :class="[
                        'h-9 rounded-lg transition-all hover:bg-white/70 dark:hover:bg-white/5',
                        isCurrentUrl(item.href) && [
                            'bg-linear-to-r font-semibold shadow-md hover:text-white data-[active=true]:text-white',
                            paletteFor(index).active,
                        ],
                    ]"
                >
                    <Link :href="item.href">
                        <component
                            :is="item.icon"
                            :class="
                                isCurrentUrl(item.href)
                                    ? 'text-white'
                                    : paletteFor(index).icon
                            "
                        />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
