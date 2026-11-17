<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContentPageController from '@/actions/App/Http/Controllers/Admin/ContentPageController';
import RichTextEditor from '@/components/admin/RichTextEditor.vue';
import InputError from '@/components/InputError.vue';
import MarkdownContent from '@/components/legal/MarkdownContent.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { AdminContentPage, ContentPlaceholder } from '@/types/legal';

const props = defineProps<{
    page?: AdminContentPage;
    placeholders: ContentPlaceholder[];
    errors: Record<string, string>;
    defaultSortOrder?: number;
}>();

const isSystem = props.page?.is_system ?? false;

const title = ref(props.page?.title ?? '');
const slug = ref(props.page?.slug ?? '');
const body = ref(props.page?.body ?? '');
const isPublished = ref(props.page?.is_published ?? true);
const showInFooter = ref(props.page?.show_in_footer ?? true);

/** New pages take their slug from the title until the slug is edited. */
const slugEdited = ref(props.page !== undefined);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

function onTitleInput(): void {
    if (!slugEdited.value) {
        slug.value = slugify(title.value);
    }
}

const preview = useHttp<{ body: string }, { html: string }>({ body: '' });
const previewHtml = ref('');

async function loadPreview(): Promise<void> {
    preview.body = body.value;

    const response = await preview.post(ContentPageController.preview.url());

    if (response) {
        previewHtml.value = response.html;
    }
}

const checkboxClass =
    'mt-0.5 size-4 rounded border-input accent-teal-600 disabled:opacity-60';
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    v-model="title"
                    name="title"
                    required
                    maxlength="120"
                    placeholder="About us"
                    @input="onTitleInput"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="slug">Page address</Label>
                <div
                    class="flex h-10 items-center rounded-md border border-input bg-background text-sm shadow-xs focus-within:ring-2 focus-within:ring-ring"
                    :class="{ 'bg-muted': isSystem }"
                >
                    <span class="pl-3 text-muted-foreground">
                        {{ isSystem ? '/' : '/pages/' }}
                    </span>
                    <input
                        id="slug"
                        v-model="slug"
                        :name="isSystem ? undefined : 'slug'"
                        :disabled="isSystem"
                        required
                        maxlength="80"
                        placeholder="about-us"
                        class="h-full min-w-0 flex-1 bg-transparent pr-3 outline-none disabled:cursor-not-allowed"
                        @input="slugEdited = true"
                    />
                </div>
                <p v-if="isSystem" class="text-xs text-muted-foreground">
                    Policy pages keep their address because payment providers
                    link to them.
                </p>
                <InputError :message="errors.slug" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="description">Short description</Label>
            <Input
                id="description"
                name="description"
                :default-value="page?.description ?? ''"
                maxlength="300"
                placeholder="Shown under the title and to search engines."
            />
            <InputError :message="errors.description" />
        </div>

        <div class="grid gap-2">
            <Label for="body">Content</Label>
            <Tabs default-value="write" class="gap-3">
                <TabsList>
                    <TabsTrigger value="write">Editor</TabsTrigger>
                    <TabsTrigger value="markdown">Markdown</TabsTrigger>
                    <TabsTrigger value="preview" @click="loadPreview">
                        Preview
                    </TabsTrigger>
                </TabsList>
                <input type="hidden" name="body" :value="body" />
                <TabsContent
                    value="write"
                    force-mount
                    class="data-[state=inactive]:hidden"
                >
                    <RichTextEditor v-model="body" />
                </TabsContent>
                <TabsContent value="markdown">
                    <textarea
                        id="body"
                        v-model="body"
                        rows="22"
                        placeholder="## Heading&#10;&#10;Write the page in Markdown..."
                        class="flex min-h-96 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </TabsContent>
                <TabsContent value="preview">
                    <div
                        class="min-h-96 rounded-md border border-border/60 bg-card p-5"
                    >
                        <div
                            v-if="preview.processing"
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Spinner class="size-4" />
                            Loading preview...
                        </div>
                        <InputError
                            v-else-if="preview.errors.body"
                            :message="preview.errors.body"
                        />
                        <MarkdownContent v-else :html="previewHtml" />
                    </div>
                </TabsContent>
            </Tabs>
            <InputError :message="errors.body" />

            <div
                class="space-y-2 rounded-md border border-border/60 bg-muted/30 p-3 text-xs text-muted-foreground"
            >
                <p>
                    Use the toolbar to format text, or edit the raw Markdown in
                    the Markdown tab. Type placeholders exactly as shown; they
                    are replaced with the business details from the server
                    settings:
                </p>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="placeholder in placeholders"
                        :key="placeholder.token"
                        class="rounded-full border border-border/60 bg-background px-2 py-0.5"
                    >
                        <code class="font-semibold text-foreground">{{
                            placeholder.token
                        }}</code>
                        → {{ placeholder.value || 'not set' }}
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-[1fr_10rem]">
            <div class="grid gap-3">
                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-if="!isSystem"
                        type="hidden"
                        name="is_published"
                        :value="isPublished ? 1 : 0"
                    />
                    <input
                        v-model="isPublished"
                        type="checkbox"
                        :class="checkboxClass"
                        :disabled="isSystem"
                    />
                    <span>
                        <span class="font-medium">Published</span>
                        <span class="block text-xs text-muted-foreground">
                            {{
                                isSystem
                                    ? 'Policy pages are always published.'
                                    : 'Drafts are only visible to admins.'
                            }}
                        </span>
                    </span>
                </label>
                <InputError :message="errors.is_published" />

                <label class="flex items-start gap-3 text-sm">
                    <input
                        type="hidden"
                        name="show_in_footer"
                        :value="showInFooter ? 1 : 0"
                    />
                    <input
                        v-model="showInFooter"
                        type="checkbox"
                        :class="checkboxClass"
                    />
                    <span>
                        <span class="font-medium">Show in footer</span>
                        <span class="block text-xs text-muted-foreground">
                            Adds a link to the site footer and policy pages.
                        </span>
                    </span>
                </label>
                <InputError :message="errors.show_in_footer" />
            </div>

            <div class="grid content-start gap-2">
                <Label for="sort_order">Footer order</Label>
                <Input
                    id="sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    max="1000"
                    required
                    :default-value="page?.sort_order ?? defaultSortOrder ?? 10"
                />
                <InputError :message="errors.sort_order" />
            </div>
        </div>
    </div>
</template>
