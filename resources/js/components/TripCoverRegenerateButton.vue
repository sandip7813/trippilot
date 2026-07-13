<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3';
import { ImageIcon, RefreshCw } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        formBinding: Record<string, unknown>;
        hasCover: boolean;
        exhausted?: boolean;
        variant?: 'outline' | 'ghost' | 'secondary';
        size?: 'sm' | 'default' | 'icon';
        class?: string;
    }>(),
    {
        variant: 'outline',
        size: 'sm',
        exhausted: false,
    },
);

const page = usePage<{
    trip: {
        cover_image_url?: string | null;
        cover_image_version?: number;
        cover_image_exhausted?: boolean;
    };
}>();

const coverImageUrl = computed(
    () => page.props.trip?.cover_image_url ?? null,
);

const coverImageVersion = computed(
    () => Number(page.props.trip?.cover_image_version ?? 0),
);

const isExhausted = computed(
    () => props.exhausted || Boolean(page.props.trip?.cover_image_exhausted),
);

const waitingForCover = ref(false);
const versionAtRequest = ref(0);

let pollInterval: ReturnType<typeof setInterval> | null = null;
let pollTimeout: ReturnType<typeof setTimeout> | null = null;

const label = computed(() => {
    if (isExhausted.value) {
        return 'No more automatic photos';
    }

    return props.hasCover
        ? 'Try another photo'
        : 'Find cover photo';
});

function clearPolling(): void {
    if (pollInterval !== null) {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    if (pollTimeout !== null) {
        clearTimeout(pollTimeout);
        pollTimeout = null;
    }
}

function stopWaiting(): void {
    waitingForCover.value = false;
    clearPolling();
}

function coverSyncFinished(version: number): boolean {
    if (!waitingForCover.value) {
        return false;
    }

    if (version > versionAtRequest.value) {
        return true;
    }

    if (!props.hasCover && coverImageUrl.value) {
        return true;
    }

    return false;
}

watch(coverImageVersion, (version) => {
    if (coverSyncFinished(version)) {
        stopWaiting();
    }
});

function pollForUpdatedCover(): void {
    router.reload({
        only: ['trip'],
        preserveScroll: true,
    });
}

function handleSuccess(): void {
    versionAtRequest.value = coverImageVersion.value;
    waitingForCover.value = true;
    clearPolling();

    pollForUpdatedCover();

    pollInterval = setInterval(pollForUpdatedCover, 2500);

    pollTimeout = setTimeout(() => {
        stopWaiting();
    }, 90_000);
}

onUnmounted(() => {
    clearPolling();
});
</script>

<template>
    <Form
        v-if="!isExhausted"
        v-bind="formBinding"
        v-slot="{ processing }"
        class="relative"
        @success="handleSuccess"
    >
        <FormSavingOverlay
            :show="processing"
            message="Searching for another photo..."
        />
        <Button
            type="submit"
            :variant="variant"
            :size="size"
            :class="cn(props.class)"
            :disabled="processing || waitingForCover"
            :title="label"
        >
            <Spinner
                v-if="processing || waitingForCover"
                :class="size === 'icon' ? 'size-4' : 'mr-1.5 size-4'"
            />
            <RefreshCw
                v-else-if="hasCover"
                :class="size === 'icon' ? 'size-4' : 'mr-1.5 size-4'"
            />
            <ImageIcon
                v-else
                :class="size === 'icon' ? 'size-4' : 'mr-1.5 size-4'"
            />
            <span v-if="size !== 'icon'">{{ label }}</span>
        </Button>
    </Form>
</template>
