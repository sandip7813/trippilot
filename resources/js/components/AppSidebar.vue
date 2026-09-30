<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Compass,
    House,
    LayoutGrid,
    BookOpen,
    ListChecks,
    Map,
    MapPinned,
    Megaphone,
    MessageCircle,
    Shield,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as knowledgeIndex } from '@/routes/admin/knowledge';
import { settings as superSettings } from '@/routes/admin/super';
import { index as tripReportsIndex } from '@/routes/admin/trip-reports';
import { index as adminTripsIndex } from '@/routes/admin/trips';
import { index as usersIndex } from '@/routes/admin/users';
import { index as assistantIndex } from '@/routes/assistant';
import { index as openTripsIndex } from '@/routes/open-trips';
import { index as roadTripsIndex } from '@/routes/road-trips';
import {
    groupTours as groupToursIndex,
    index as tripsIndex,
} from '@/routes/trips';
import type { Auth, NavItem } from '@/types';

const page = usePage<{ auth: Auth }>();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Trips',
        href: tripsIndex(),
        icon: Map,
    },
    {
        title: 'Road Trips',
        href: roadTripsIndex(),
        icon: MapPinned,
    },
    {
        title: 'Group Tours',
        href: groupToursIndex(),
        icon: Megaphone,
    },
    {
        title: 'Travel assistant',
        href: assistantIndex(),
        icon: MessageCircle,
    },
];

const publicNavItems: NavItem[] = [
    {
        title: 'Home',
        href: home(),
        icon: House,
    },
    {
        title: 'Discover',
        href: openTripsIndex(),
        icon: Compass,
    },
];

const adminNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user || (user.role !== 'admin' && user.role !== 'super_admin')) {
        return [];
    }

    const items: NavItem[] = [
        {
            title: 'Admin',
            href: adminDashboard(),
            icon: Shield,
        },
        {
            title: 'Knowledge Base',
            href: knowledgeIndex(),
            icon: BookOpen,
        },
        {
            title: 'Users',
            href: usersIndex(),
            icon: Users,
        },
        {
            title: 'Trip Moderation',
            href: adminTripsIndex(),
            icon: ListChecks,
        },
        {
            title: 'Reported Trips',
            href: tripReportsIndex(),
            icon: ShieldCheck,
        },
    ];

    if (user.role === 'super_admin') {
        items.push({
            title: 'Super Admin',
            href: superSettings(),
            icon: ShieldCheck,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" />
            <NavMain :items="publicNavItems" label="Public" />
            <NavMain
                v-if="adminNavItems.length"
                :items="adminNavItems"
                label="Administration"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
