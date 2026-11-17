<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, FileText, Plus } from '@lucide/vue';
import ContentPageController from '@/actions/App/Http/Controllers/Admin/ContentPageController';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create,
    index as contentPagesIndex,
} from '@/routes/admin/content-pages';
import type { AdminContentPage } from '@/types/legal';

defineProps<{
    pages: AdminContentPage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Content pages', href: contentPagesIndex() },
        ],
    },
});

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Content pages" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Content pages"
            description="Edit the Terms, Privacy and Refund policy pages, or add pages such as About us. Pages marked for the footer are linked on every public page."
            :icon="FileText"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        Add page
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="p-0">
                <div class="divide-y divide-border/60">
                    <div
                        v-for="page in pages"
                        :key="page.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        data-test="content-page-row"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="
                                        ContentPageController.edit.url(page.id)
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ page.title }}
                                </Link>
                                <Badge
                                    v-if="page.is_system"
                                    variant="secondary"
                                >
                                    Policy
                                </Badge>
                                <Badge
                                    v-if="!page.is_published"
                                    variant="outline"
                                >
                                    Draft
                                </Badge>
                                <Badge
                                    v-if="page.show_in_footer"
                                    variant="outline"
                                >
                                    In footer
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ page.url }}
                            </p>
                            <p
                                v-if="page.updated_at"
                                class="text-xs text-muted-foreground"
                            >
                                Updated {{ formatDate(page.updated_at) }}
                                <span v-if="page.updated_by">
                                    by {{ page.updated_by }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <Button variant="ghost" size="sm" as-child>
                                <a
                                    :href="page.url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <ExternalLink class="size-4" />
                                    View
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="
                                        ContentPageController.edit.url(page.id)
                                    "
                                >
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
