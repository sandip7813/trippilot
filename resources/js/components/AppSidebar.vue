<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Coins,
    Compass,
    FileText,
    Flag,
    HandCoins,
    History,
    House,
    LayoutGrid,
    Mail,
    BookOpen,
    Inbox,
    ListChecks,
    Map,
    MapPinned,
    Megaphone,
    MessageCircle,
    Receipt,
    Shield,
    ShieldCheck,
    ShoppingCart,
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
import { contact, dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as contentPagesIndex } from '@/routes/admin/content-pages';
import { index as knowledgeIndex } from '@/routes/admin/knowledge';
import { settings as superSettings } from '@/routes/admin/super';
import { index as contactMessagesIndex } from '@/routes/admin/super/contact-messages';
import { index as creditGrantsIndex } from '@/routes/admin/super/credit-grants';
import { index as creditPurchasesIndex } from '@/routes/admin/super/credit-purchases';
import { show as superCredits } from '@/routes/admin/super/credits';
import { index as tripReportsIndex } from '@/routes/admin/trip-reports';
import { index as adminTripsIndex } from '@/routes/admin/trips';
import { index as usersIndex } from '@/routes/admin/users';
import { index as assistantIndex } from '@/routes/assistant';
import {
    buy as buyCredits,
    costs as creditCosts,
    history as creditHistory,
} from '@/routes/credits';
import { index as openTripsIndex } from '@/routes/open-trips';
import { index as roadTripsIndex } from '@/routes/road-trips';
import {
    groupTours as groupToursIndex,
    index as tripsIndex,
} from '@/routes/trips';
import type { Auth, NavItem } from '@/types';

const page = usePage<{ auth: Auth }>();

const overviewNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const planNavItems: NavItem[] = [
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
];

const aiNavItems: NavItem[] = [
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
    {
        title: 'Contact',
        href: contact(),
        icon: Mail,
    },
];

const creditNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user || user.role === 'admin' || user.role === 'super_admin') {
        return [];
    }

    return [
        {
            title: 'Credit costs',
            href: creditCosts(),
            icon: ListChecks,
        },
        {
            title: 'Buy credits',
            href: buyCredits(),
            icon: ShoppingCart,
        },
        {
            title: 'Credit history',
            href: creditHistory(),
            icon: History,
        },
    ];
});

type NavSection = {
    label: string;
    items: NavItem[];
};

/** Admin pages grouped by area; billing and system pages are super admin only. */
const adminNavSections = computed<NavSection[]>(() => {
    const user = page.props.auth.user;

    if (!user || (user.role !== 'admin' && user.role !== 'super_admin')) {
        return [];
    }

    const sections: NavSection[] = [
        {
            label: 'Admin',
            items: [
                {
                    title: 'Admin dashboard',
                    href: adminDashboard(),
                    icon: Shield,
                },
            ],
        },
        {
            label: 'Moderation',
            items: [
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
                    icon: Flag,
                },
            ],
        },
        {
            label: 'Content',
            items: [
                {
                    title: 'Content pages',
                    href: contentPagesIndex(),
                    icon: FileText,
                },
                {
                    title: 'Knowledge Base',
                    href: knowledgeIndex(),
                    icon: BookOpen,
                },
            ],
        },
    ];

    if (user.role === 'super_admin') {
        sections.push(
            {
                label: 'Billing',
                items: [
                    {
                        title: 'Credits Settings',
                        href: superCredits(),
                        icon: Coins,
                    },
                    {
                        title: 'Credit Purchases',
                        href: creditPurchasesIndex(),
                        icon: Receipt,
                    },
                    {
                        title: 'Credits Added',
                        href: creditGrantsIndex(),
                        icon: HandCoins,
                    },
                ],
            },
            {
                label: 'Support & system',
                items: [
                    {
                        title: 'Contact Messages',
                        href: contactMessagesIndex(),
                        icon: Inbox,
                    },
                    {
                        title: 'Platform settings',
                        href: superSettings(),
                        icon: ShieldCheck,
                    },
                ],
            },
        );
    }

    return sections;
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
            <NavMain :items="overviewNavItems" label="Overview" />
            <NavMain :items="planNavItems" label="Plan & travel" />
            <NavMain :items="aiNavItems" label="AI tools" />
            <NavMain
                v-if="creditNavItems.length"
                :items="creditNavItems"
                label="Credits"
            />
            <NavMain
                v-for="section in adminNavSections"
                :key="section.label"
                :items="section.items"
                :label="section.label"
            />
            <NavMain :items="publicNavItems" label="Explore" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
