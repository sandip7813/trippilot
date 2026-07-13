<script setup lang="ts">
import { MapPin } from '@lucide/vue';
import TripCoverRegenerateButton from '@/components/TripCoverRegenerateButton.vue';
import TripCoverUploadButton from '@/components/TripCoverUploadButton.vue';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

defineProps<{
    exhausted: boolean;
    pending?: boolean;
    syncCoverForm: Record<string, unknown>;
    uploadCoverForm: Record<string, unknown>;
    class?: string;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'relative overflow-hidden rounded-2xl border border-dashed border-border/70 bg-gradient-to-br from-muted/60 via-muted/30 to-background',
                $props.class,
            )
        "
    >
        <div
            class="flex min-h-40 flex-col items-center justify-center gap-3 px-4 py-8 text-center md:min-h-52"
        >
            <div
                class="flex size-12 items-center justify-center rounded-full bg-background/80 shadow-sm"
            >
                <MapPin class="size-6 text-muted-foreground" />
            </div>
            <div class="space-y-1">
                <p class="text-sm font-medium text-foreground">
                    {{
                        pending
                            ? 'Searching for a cover photo...'
                            : exhausted
                              ? 'No suitable photo found automatically'
                              : 'Destination cover photo'
                    }}
                </p>
                <p class="max-w-md text-xs text-muted-foreground">
                    {{
                        pending
                            ? 'Checking Wikipedia, Wikimedia Commons, Unsplash, and other sources.'
                            : exhausted
                              ? 'Upload your own photo for this place.'
                              : 'We search Wikipedia, Wikimedia Commons, Unsplash, and other sources.'
                    }}
                </p>
            </div>
            <Spinner v-if="pending" class="size-6 text-muted-foreground" />
            <div
                v-else
                class="flex flex-wrap items-center justify-center gap-2"
            >
                <TripCoverRegenerateButton
                    :form-binding="syncCoverForm"
                    :has-cover="false"
                    :exhausted="exhausted"
                />
                <TripCoverUploadButton
                    v-if="exhausted"
                    :form-binding="uploadCoverForm"
                />
            </div>
        </div>
    </div>
</template>
