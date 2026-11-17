<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Mail, MapPin, Pencil, Phone } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { Button } from '@/components/ui/button';
import { contact, pricing } from '@/routes';
import type { FooterPageLink, LegalDetails } from '@/types/legal';

defineProps<{
    title: string;
    description?: string | null;
    icon: Component;
    legal: LegalDetails;
    updatedAt?: string | null;
    editUrl?: string | null;
}>();

const page = usePage();

const policyLinks = computed<FooterPageLink[]>(() => [
    { title: 'Pricing', href: pricing().url },
    ...((page.props.footerPages as FooterPageLink[] | undefined) ?? []),
]);

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="title">
        <meta v-if="description" name="description" :content="description" />
    </Head>

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <PublicHeader container-class="max-w-4xl" />

        <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-10 sm:px-6">
            <header
                class="mb-8 rounded-2xl border border-white/70 bg-linear-to-br from-teal-50 via-sky-50 to-violet-100 p-6 shadow-sm dark:border-white/10 dark:from-teal-500/15 dark:via-sky-500/10 dark:to-violet-500/20"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-teal-500 to-emerald-500 text-white shadow-md shadow-teal-500/30"
                    >
                        <component :is="icon" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h1
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                        <p
                            v-if="updatedAt"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Last updated: {{ formatDate(updatedAt) }}
                        </p>
                    </div>
                    <Button
                        v-if="editUrl"
                        variant="outline"
                        size="sm"
                        as-child
                        class="shrink-0"
                    >
                        <Link :href="editUrl">
                            <Pencil class="size-4" />
                            Edit
                        </Link>
                    </Button>
                </div>
            </header>

            <slot />

            <section
                class="mt-10 rounded-2xl border border-border/60 bg-card p-5 shadow-sm"
                data-test="legal-contact"
            >
                <h2 class="font-semibold">Contact us</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    TripPilot is operated by {{ legal.business_name }}. For any
                    question about these terms, your data or a payment, reach us
                    through the
                    <Link :href="contact()" class="font-medium underline">
                        contact page
                    </Link>
                    or:
                </p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li
                        v-if="legal.support_email"
                        class="flex items-center gap-2"
                    >
                        <Mail class="size-4 text-teal-600" />
                        <a
                            :href="`mailto:${legal.support_email}`"
                            class="underline"
                        >
                            {{ legal.support_email }}
                        </a>
                    </li>
                    <li
                        v-if="legal.support_phone"
                        class="flex items-center gap-2"
                    >
                        <Phone class="size-4 text-teal-600" />
                        {{ legal.support_phone }}
                    </li>
                    <li v-if="legal.address" class="flex items-start gap-2">
                        <MapPin class="mt-0.5 size-4 shrink-0 text-teal-600" />
                        <span class="whitespace-pre-line">{{
                            legal.address
                        }}</span>
                    </li>
                </ul>
            </section>

            <nav
                class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground"
                aria-label="Policies"
            >
                <Link
                    v-for="link in policyLinks"
                    :key="link.href"
                    :href="link.href"
                    class="hover:text-foreground hover:underline"
                >
                    {{ link.title }}
                </Link>
            </nav>
        </main>

        <PublicFooter container-class="max-w-4xl" />
    </div>
</template>
