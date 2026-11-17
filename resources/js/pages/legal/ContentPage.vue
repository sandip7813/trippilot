<script setup lang="ts">
import { FileText, ReceiptText, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import LegalPage from '@/components/legal/LegalPage.vue';
import MarkdownContent from '@/components/legal/MarkdownContent.vue';
import type { LegalDetails, PublicContentPage } from '@/types/legal';

const props = defineProps<{
    legal: LegalDetails;
    page: PublicContentPage;
    edit_url: string | null;
}>();

const icon = computed(
    () =>
        ({ privacy: ShieldCheck, 'refund-policy': ReceiptText })[
            props.page.slug
        ] ?? FileText,
);
</script>

<template>
    <LegalPage
        :title="page.title"
        :description="page.description"
        :icon="icon"
        :legal="legal"
        :updated-at="page.updated_at"
        :edit-url="edit_url"
    >
        <p
            v-if="!page.is_published"
            class="mb-6 rounded-xl border border-amber-500/40 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
        >
            This page is a draft. Only admins can see it.
        </p>
        <MarkdownContent :html="page.html" data-test="content-page-body" />
    </LegalPage>
</template>
