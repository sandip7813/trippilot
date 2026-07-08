<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Map, MapPinned, Shield, ShieldCheck } from '@lucide/vue';
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
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { settings as superSettings } from '@/routes/admin/super';
import { index as roadTripsIndex } from '@/routes/road-trips';
import { index as tripsIndex } from '@/routes/trips';
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
