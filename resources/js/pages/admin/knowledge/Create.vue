<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import KnowledgeDocumentController from '@/actions/App/Http/Controllers/Admin/KnowledgeDocumentController';
import KnowledgeDocumentFormFields from '@/components/admin/KnowledgeDocumentFormFields.vue';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index as knowledgeIndex } from '@/routes/admin/knowledge';
import type { KnowledgeDocumentOption } from '@/types/knowledge';
import { dashboard as adminDashboard } from '@/routes/admin';

defineProps<{
    statuses: KnowledgeDocumentOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Knowledge Base', href: knowledgeIndex() },
            { title: 'Add document' },
        ],
    },
});
</script>

<template>
    <Head title="Add Knowledge Document" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Add knowledge document"
            description="Published documents are chunked, embedded, and retrieved during AI chat and itinerary generation."
        />

        <Form
            v-bind="KnowledgeDocumentController.store.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Saving and indexing document..."
            />

            <Card>
                <CardContent class="pt-6">
                    <KnowledgeDocumentFormFields
                        :statuses="statuses"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" class="mr-2" />
                    Create document
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="knowledgeIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Cancel
                    </Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
