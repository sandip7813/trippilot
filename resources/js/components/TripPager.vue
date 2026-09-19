<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/admin';

defineProps<{
    paginated: Paginated<unknown>;
    only: string[];
}>();
</script>

<template>
    <div
        v-if="paginated.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-3"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ paginated.from ?? 0 }}–{{ paginated.to ?? 0 }} of
            {{ paginated.total }}
        </p>

        <div class="flex flex-wrap gap-2">
            <template
                v-for="link in paginated.links"
                :key="`${link.label}-${link.url}`"
            >
                <Button
                    v-if="link.url"
                    as-child
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                >
                    <Link
                        :href="link.url"
                        :only="only"
                        preserve-state
                        preserve-scroll
                    >
                        <span v-html="link.label" />
                    </Link>
                </Button>
                <Button v-else size="sm" variant="outline" disabled>
                    <span v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>
</template>
