<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { KnowledgeDocument, KnowledgeDocumentOption } from '@/types/knowledge';
import { destinationsInputValue } from '@/types/knowledge';

const props = defineProps<{
    document?: KnowledgeDocument;
    statuses: KnowledgeDocumentOption[];
    errors: Record<string, string>;
}>();

const destinations = ref(destinationsInputValue(props.document?.destinations));
const content = ref(props.document?.content ?? '');
const status = ref(props.document?.status ?? 'draft');
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="title">Title</Label>
            <Input
                id="title"
                name="title"
                :default-value="document?.title"
                required
                placeholder="Goa beach guide"
            />
            <InputError :message="errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="destinations">Destinations</Label>
            <Input
                id="destinations"
                v-model="destinations"
                name="destinations"
                required
                placeholder="goa, panaji, margao"
            />
            <p class="text-xs text-muted-foreground">
                Comma-separated destination tags used for retrieval filtering.
            </p>
            <InputError :message="errors.destinations" />
        </div>

        <div class="grid gap-2">
            <Label for="status">Status</Label>
            <select
                id="status"
                name="status"
                v-model="status"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option
                    v-for="option in statuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="errors.status" />
        </div>

        <div class="grid gap-2">
            <Label for="content">Content</Label>
            <textarea
                id="content"
                v-model="content"
                name="content"
                rows="16"
                required
                placeholder="Write practical travel guidance in plain text or markdown..."
                class="flex min-h-64 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            />
            <InputError :message="errors.content" />
        </div>
    </div>
</template>
