<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Plus } from '@lucide/vue';
import KnowledgeDocumentController from '@/actions/App/Http/Controllers/Admin/KnowledgeDocumentController';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import { create, index as knowledgeIndex } from '@/routes/admin/knowledge';
import type { KnowledgeDocument } from '@/types/knowledge';

defineProps<{
    documents: KnowledgeDocument[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Knowledge Base', href: knowledgeIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Knowledge Base" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Knowledge Base"
            description="Travel guides and destination notes used by TripPilot RAG."
            :icon="BookOpen"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 size-4" />
                        Add document
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="p-0">
                <div
                    v-if="documents.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No knowledge documents yet. Add your first guide to improve
                    AI answers and itineraries.
                </div>

                <div v-else class="divide-y divide-border/60">
                    <div
                        v-for="document in documents"
                        :key="document.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="
                                        KnowledgeDocumentController.edit.url(
                                            document.id,
                                        )
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ document.title }}
                                </Link>
                                <Badge variant="outline">
                                    {{ document.status_label }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ document.destinations.join(', ') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ document.chunk_count }} chunks
                                <span v-if="document.updated_at">
                                    · Updated {{ document.updated_at }}
                                </span>
                            </p>
                        </div>

                        <Button variant="outline" as-child class="shrink-0">
                            <Link
                                :href="
                                    KnowledgeDocumentController.edit.url(
                                        document.id,
                                    )
                                "
                            >
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Button variant="outline" as-child>
            <Link :href="adminDashboard()">
                <ArrowLeft class="mr-2 size-4" />
                Back to admin
            </Link>
        </Button>
    </div>
</template>
