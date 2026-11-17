<script setup lang="ts">
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
} from '@lucide/vue';
import { Markdown } from '@tiptap/markdown';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { contentProseClass } from '@/lib/contentProse';
import { cn } from '@/lib/utils';

/**
 * WYSIWYG editor that reads and writes Markdown, so saved pages keep going
 * through the server's Markdown renderer (which strips any HTML).
 */
const markdown = defineModel<string>({ required: true });

/**
 * Undoes escaping the serializer adds that would break the page: `\_`
 * inside placeholders such as {business_name}, and `&amp;` for a plain "&".
 */
function normalizeMarkdown(value: string): string {
    return value
        .replace(
            /\{([a-z\\_]+)\}/g,
            (_match, key: string) => `{${key.replaceAll('\\', '')}}`,
        )
        .replaceAll('&amp;', '&');
}

function editorMarkdown(): string {
    return normalizeMarkdown(editor.value?.getMarkdown() ?? '');
}

const editor = useEditor({
    content: markdown.value,
    contentType: 'markdown',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            underline: false,
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
            },
        }),
        Markdown,
    ],
    editorProps: {
        attributes: {
            class: cn(
                contentProseClass,
                'min-h-96 px-4 py-3 text-foreground outline-none',
            ),
        },
    },
    onUpdate: () => {
        markdown.value = editorMarkdown();
    },
});

/** Keeps the editor in sync when the Markdown is changed elsewhere. */
watch(markdown, (value) => {
    const instance = editor.value;

    if (instance && editorMarkdown() !== value) {
        instance.commands.setContent(value, {
            contentType: 'markdown',
            emitUpdate: false,
        });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const linkUrl = ref('');
const linkOpen = ref(false);

function openLinkEditor(open: boolean): void {
    linkOpen.value = open;

    if (open) {
        linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    }
}

function applyLink(): void {
    const url = linkUrl.value.trim();
    const chain = editor.value?.chain().focus().extendMarkRange('link');

    if (url === '') {
        chain?.unsetLink().run();
    } else {
        chain?.setLink({ href: url }).run();
    }

    linkOpen.value = false;
}

type ToolbarAction = {
    label: string;
    icon: Component;
    run: () => void;
    isActive?: () => boolean;
    isDisabled?: () => boolean;
};

const blockActions: ToolbarAction[] = [
    {
        label: 'Paragraph',
        icon: Pilcrow,
        run: () => editor.value?.chain().focus().setParagraph().run(),
        isActive: () => editor.value?.isActive('paragraph') ?? false,
    },
    {
        label: 'Heading',
        icon: Heading2,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        isActive: () =>
            editor.value?.isActive('heading', { level: 2 }) ?? false,
    },
    {
        label: 'Subheading',
        icon: Heading3,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        isActive: () =>
            editor.value?.isActive('heading', { level: 3 }) ?? false,
    },
];

const markActions: ToolbarAction[] = [
    {
        label: 'Bold',
        icon: Bold,
        run: () => editor.value?.chain().focus().toggleBold().run(),
        isActive: () => editor.value?.isActive('bold') ?? false,
    },
    {
        label: 'Italic',
        icon: Italic,
        run: () => editor.value?.chain().focus().toggleItalic().run(),
        isActive: () => editor.value?.isActive('italic') ?? false,
    },
    {
        label: 'Strikethrough',
        icon: Strikethrough,
        run: () => editor.value?.chain().focus().toggleStrike().run(),
        isActive: () => editor.value?.isActive('strike') ?? false,
    },
];

const structureActions: ToolbarAction[] = [
    {
        label: 'Bulleted list',
        icon: List,
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
        isActive: () => editor.value?.isActive('bulletList') ?? false,
    },
    {
        label: 'Numbered list',
        icon: ListOrdered,
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        isActive: () => editor.value?.isActive('orderedList') ?? false,
    },
    {
        label: 'Quote',
        icon: Quote,
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        isActive: () => editor.value?.isActive('blockquote') ?? false,
    },
    {
        label: 'Divider',
        icon: Minus,
        run: () => editor.value?.chain().focus().setHorizontalRule().run(),
    },
];

const historyActions: ToolbarAction[] = [
    {
        label: 'Undo',
        icon: Undo2,
        run: () => editor.value?.chain().focus().undo().run(),
        isDisabled: () => !(editor.value?.can().undo() ?? false),
    },
    {
        label: 'Redo',
        icon: Redo2,
        run: () => editor.value?.chain().focus().redo().run(),
        isDisabled: () => !(editor.value?.can().redo() ?? false),
    },
];

const toolbarGroups = [blockActions, markActions, structureActions];

function buttonClass(action: ToolbarAction): string {
    return action.isActive?.()
        ? 'bg-teal-100 text-teal-800 hover:bg-teal-100 dark:bg-teal-500/20 dark:text-teal-200'
        : 'text-muted-foreground';
}
</script>

<template>
    <div
        class="overflow-hidden rounded-md border border-input bg-background shadow-xs focus-within:ring-2 focus-within:ring-ring"
        data-test="rich-text-editor"
    >
        <div
            class="flex flex-wrap items-center gap-0.5 border-b border-border/60 bg-muted/40 p-1"
            role="toolbar"
            aria-label="Formatting"
        >
            <template
                v-for="(group, groupIndex) in toolbarGroups"
                :key="groupIndex"
            >
                <Button
                    v-for="action in group"
                    :key="action.label"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :class="buttonClass(action)"
                    :title="action.label"
                    :aria-label="action.label"
                    :aria-pressed="action.isActive?.() ?? undefined"
                    @click="action.run"
                >
                    <component :is="action.icon" class="size-4" />
                </Button>
                <span class="mx-1 h-5 w-px bg-border" />
            </template>

            <Popover :open="linkOpen" @update:open="openLinkEditor">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :class="
                            editor?.isActive('link')
                                ? 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-200'
                                : 'text-muted-foreground'
                        "
                        title="Link"
                        aria-label="Link"
                    >
                        <Link2 class="size-4" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="start" class="w-80 space-y-2 p-3">
                    <p class="text-xs text-muted-foreground">
                        Select text first, then add a full address or a
                        TripPilot path such as /contact.
                    </p>
                    <form class="flex gap-2" @submit.prevent="applyLink">
                        <Input
                            v-model="linkUrl"
                            placeholder="https://… or /contact"
                            class="h-8"
                        />
                        <Button type="submit" size="sm">Apply</Button>
                    </form>
                </PopoverContent>
            </Popover>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground"
                title="Remove link"
                aria-label="Remove link"
                :disabled="!editor?.isActive('link')"
                @click="editor?.chain().focus().unsetLink().run()"
            >
                <Link2Off class="size-4" />
            </Button>

            <span class="mx-1 h-5 w-px bg-border" />

            <Button
                v-for="action in historyActions"
                :key="action.label"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground"
                :title="action.label"
                :aria-label="action.label"
                :disabled="action.isDisabled?.()"
                @click="action.run"
            >
                <component :is="action.icon" class="size-4" />
            </Button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>
