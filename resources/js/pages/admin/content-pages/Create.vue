<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import ContentPageController from '@/actions/App/Http/Controllers/Admin/ContentPageController';
import ContentPageFormFields from '@/components/admin/ContentPageFormFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as contentPagesIndex } from '@/routes/admin/content-pages';
import type { ContentPlaceholder } from '@/types/legal';

defineProps<{
    placeholders: ContentPlaceholder[];
    next_sort_order: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Content pages', href: contentPagesIndex() },
            { title: 'Add page' },
        ],
    },
});
</script>

<template>
    <Head title="Add page" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Add page"
            description="New pages are served at /pages/your-address."
        />

        <Form
            v-bind="ContentPageController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <Card>
                <CardContent class="pt-6">
                    <ContentPageFormFields
                        :placeholders="placeholders"
                        :errors="errors"
                        :default-sort-order="next_sort_order"
                    />
                </CardContent>
            </Card>

            <div class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Create page
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="contentPagesIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
