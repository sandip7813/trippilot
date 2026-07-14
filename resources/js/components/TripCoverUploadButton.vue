<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import FormSavingOverlay from '@/components/FormSavingOverlay.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        formBinding: Record<string, unknown>;
        variant?: 'outline' | 'ghost' | 'secondary' | 'default';
        size?: 'sm' | 'default' | 'icon';
        class?: string;
    }>(),
    {
        variant: 'outline',
        size: 'sm',
    },
);

const page = usePage<{
    trip: {
        cover_image_version?: number;
    };
}>();

const coverImageVersion = computed(() =>
    Number(page.props.trip?.cover_image_version ?? 0),
);

const waitingForUpload = ref(false);
const versionAtRequest = ref(0);

let pollInterval: ReturnType<typeof setInterval> | null = null;
let pollTimeout: ReturnType<typeof setTimeout> | null = null;

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
    waitingForUpload.value = false;
    clearPolling();
}

watch(coverImageVersion, (version) => {
    if (waitingForUpload.value && version > versionAtRequest.value) {
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
    waitingForUpload.value = true;
    clearPolling();

    pollForUpdatedCover();

    pollInterval = setInterval(pollForUpdatedCover, 2000);

    pollTimeout = setTimeout(() => {
        stopWaiting();
    }, 30_000);
}

onUnmounted(() => {
    clearPolling();
});
</script>

<template>
    <Form
        v-bind="formBinding"
        v-slot="{ processing }"
        :options="{ forceFormData: true }"
        class="relative"
        @success="handleSuccess"
    >
        <FormSavingOverlay
            :show="processing"
            message="Uploading cover image..."
        />
        <Button
            type="button"
            :variant="variant"
            :size="size"
            :class="cn('relative', props.class)"
            :disabled="processing || waitingForUpload"
            as-child
        >
            <label class="cursor-pointer">
                <Spinner
                    v-if="processing || waitingForUpload"
                    :class="size === 'icon' ? 'size-4' : 'mr-1.5 size-4'"
                />
                <Upload
                    v-else
                    :class="size === 'icon' ? 'size-4' : 'mr-1.5 size-4'"
                />
                <span v-if="size !== 'icon'">Upload photo</span>
                <input
                    type="file"
                    name="cover"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    :disabled="processing || waitingForUpload"
                    @change="
                        (
                            $event.target as HTMLInputElement
                        ).form?.requestSubmit()
                    "
                />
            </label>
        </Button>
    </Form>
</template>
