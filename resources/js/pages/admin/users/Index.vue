<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Users } from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types/admin';

defineProps<{
    users: Paginated<AdminUser>;
}>();

const updatingUserIds = ref<number[]>([]);

const selectClass =
    'flex h-9 min-w-36 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

function formatJoinedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function roleBadgeVariant(
    role: AdminUser['role'],
): 'default' | 'secondary' | 'outline' {
    if (role === 'super_admin') {
        return 'default';
    }

    if (role === 'admin') {
        return 'secondary';
    }

    return 'outline';
}

function updateRole(user: AdminUser, role: string): void {
    if (!user.can_update_role || role === user.role) {
        return;
    }

    updatingUserIds.value.push(user.id);

    router.patch(
        UserController.update.url(user.id),
        { role },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingUserIds.value = updatingUserIds.value.filter(
                    (id) => id !== user.id,
                );
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Users', href: usersIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Review accounts and manage platform roles."
            :icon="Users"
        />

        <Card>
            <CardContent class="p-0">
                <div
                    v-if="users.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No users found.
                </div>

                <div v-else class="divide-y divide-border/60">
                    <div
                        v-for="user in users.data"
                        :key="user.id"
                        class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium">{{ user.name }}</p>
                                <Badge
                                    v-if="user.is_self"
                                    variant="outline"
                                    class="text-xs"
                                >
                                    You
                                </Badge>
                                <Badge
                                    :variant="roleBadgeVariant(user.role)"
                                    class="text-xs lg:hidden"
                                >
                                    {{ user.role_label }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ user.email }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Joined {{ formatJoinedAt(user.created_at) }}
                                <span v-if="user.email_verified_at">
                                    · Verified
                                </span>
                            </p>
                        </div>

                        <div
                            class="flex shrink-0 items-center gap-3 self-start lg:self-center"
                        >
                            <Spinner
                                v-if="updatingUserIds.includes(user.id)"
                                class="size-4"
                            />

                            <select
                                v-if="user.can_update_role"
                                :value="user.role"
                                :disabled="updatingUserIds.includes(user.id)"
                                :class="selectClass"
                                @change="
                                    updateRole(
                                        user,
                                        (
                                            $event.target as HTMLSelectElement
                                        ).value,
                                    )
                                "
                            >
                                <option
                                    v-for="option in user.assignable_roles"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <Badge
                                v-else
                                :variant="roleBadgeVariant(user.role)"
                            >
                                {{ user.role_label }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="users.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of
                {{ users.total }}
            </p>

            <div class="flex flex-wrap gap-2">
                <template v-for="link in users.links" :key="`${link.label}-${link.url}`">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link :href="link.url" preserve-scroll>
                            <span v-html="link.label" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        disabled
                    >
                        <span v-html="link.label" />
                    </Button>
                </template>
            </div>
        </div>

        <Button variant="outline" as-child>
            <Link :href="adminDashboard()">
                <ArrowLeft class="mr-2 size-4" />
                Back to admin
            </Link>
        </Button>
    </div>
</template>
