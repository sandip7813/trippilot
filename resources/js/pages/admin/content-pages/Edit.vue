<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Trash2 } from '@lucide/vue';
import ContentPageController from '@/actions/App/Http/Controllers/Admin/ContentPageController';
import ContentPageFormFields from '@/components/admin/ContentPageFormFields.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as contentPagesIndex } from '@/routes/admin/content-pages';
import type { AdminContentPage, ContentPlaceholder } from '@/types/legal';

defineProps<{
    page: AdminContentPage;
    placeholders: ContentPlaceholder[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Content pages', href: contentPagesIndex() },
            { title: 'Edit page' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${page.title}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="page.title"
            :description="`Public address: ${page.url}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="page.url" target="_blank" rel="noopener">
                        <ExternalLink class="mr-2 size-4" />
                        View page
                    </a>
                </Button>
            </template>
        </PageHeader>

        <Form
            :key="page.updated_at ?? page.id"
            v-bind="ContentPageController.update.form(page.id)"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <Card>
                <CardContent class="pt-6">
                    <ContentPageFormFields
                        :page="page"
                        :placeholders="placeholders"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <div class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Save changes
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="contentPagesIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Link>
                </Button>

                <Dialog v-if="!page.is_system">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="ml-auto">
                            <Trash2 class="mr-2 size-4" />
                            Delete
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete this page?</DialogTitle>
                            <DialogDescription>
                                "{{ page.title }}" will be removed and its
                                address ({{ page.url }}) will stop working.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Form
                                v-bind="
                                    ContentPageController.destroy.form(page.id)
                                "
                                v-slot="{ processing: deleting }"
                            >
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="deleting"
                                >
                                    Delete page
                                </Button>
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </Form>
    </div>
</template>
