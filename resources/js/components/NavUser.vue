<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentUrl } = useCurrentUrl();

function handleLogout(): void {
    router.flushAll();
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <SidebarMenuButton
                size="lg"
                class="pointer-events-none hover:bg-transparent"
            >
                <UserInfo :user="user" />
            </SidebarMenuButton>
        </SidebarMenuItem>

        <SidebarMenuItem>
            <SidebarMenuButton
                as-child
                :is-active="isCurrentUrl(edit())"
                tooltip="Settings"
            >
                <Link :href="edit()" prefetch>
                    <Settings />
                    <span>Settings</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>

        <SidebarMenuItem>
            <SidebarMenuButton as-child tooltip="Log out">
                <Link
                    :href="logout()"
                    @click="handleLogout"
                    as="button"
                    data-test="logout-button"
                >
                    <LogOut />
                    <span>Log out</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
