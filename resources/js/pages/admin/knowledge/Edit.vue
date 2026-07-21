<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import KnowledgeDocumentController from '@/actions/App/Http/Controllers/Admin/KnowledgeDocumentController';
import KnowledgeDocumentFormFields from '@/components/admin/KnowledgeDocumentFormFields.vue';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
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
import { index as knowledgeIndex } from '@/routes/admin/knowledge';
import type {
    KnowledgeDocument,
    KnowledgeDocumentOption,
} from '@/types/knowledge';
import { dashboard as adminDashboard } from '@/routes/admin';

defineProps<{
    document: KnowledgeDocument;
    statuses: KnowledgeDocumentOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Knowledge Base', href: knowledgeIndex() },
            { title: 'Edit document' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${document.title}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="document.title"
            description="Saving re-chunks the document and refreshes embeddings."
        />

        <Form
            v-bind="KnowledgeDocumentController.update.form(document.id)"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <FormSavingOverlay
                :show="processing"
                message="Saving and re-indexing document..."
            />

            <Card>
                <CardContent class="pt-6">
                    <KnowledgeDocumentFormFields
                        :document="document"
                        :statuses="statuses"
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
                    <Link :href="knowledgeIndex()">
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Link>
                </Button>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="ml-auto">
                            <Trash2 class="mr-2 size-4" />
                            Delete
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete this document?</DialogTitle>
                            <DialogDescription>
                                This removes "{{ document.title }}" and all
                                indexed chunks. AI retrieval will no longer use
                                this content.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Form
                                v-bind="
                                    KnowledgeDocumentController.destroy.form(
                                        document.id,
                                    )
                                "
                                v-slot="{ processing: deleting }"
                            >
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="deleting"
                                >
                                    Delete document
                                </Button>
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </Form>
    </div>
</template>
