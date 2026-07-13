import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type TripCoverProps = {
    cover_image_url?: string | null;
    cover_image_version?: number;
    cover_image_exhausted?: boolean;
};

export function useTripCoverAutoRefresh() {
    const page = usePage<{ trip?: TripCoverProps }>();

    const coverImageUrl = computed(
        () => page.props.trip?.cover_image_url ?? null,
    );

    const coverImageVersion = computed(() =>
        Number(page.props.trip?.cover_image_version ?? 0),
    );

    const isExhausted = computed(() =>
        Boolean(page.props.trip?.cover_image_exhausted),
    );

    const waitingForCover = ref(false);
    const versionAtStart = ref(0);

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
        waitingForCover.value = false;
        clearPolling();
    }

    function pollForUpdatedCover(): void {
        router.reload({
            only: ['trip'],
            preserveScroll: true,
        });
    }

    function startWaiting(): void {
        if (coverImageUrl.value || isExhausted.value) {
            return;
        }

        versionAtStart.value = coverImageVersion.value;
        waitingForCover.value = true;
        clearPolling();

        pollForUpdatedCover();

        pollInterval = setInterval(pollForUpdatedCover, 2500);

        pollTimeout = setTimeout(() => {
            stopWaiting();
        }, 90_000);
    }

    watch(coverImageVersion, (version) => {
        if (!waitingForCover.value) {
            return;
        }

        if (version > versionAtStart.value || coverImageUrl.value) {
            stopWaiting();
        }
    });

    watch(isExhausted, (exhausted) => {
        if (waitingForCover.value && exhausted) {
            stopWaiting();
        }
    });

    onMounted(() => {
        startWaiting();
    });

    onUnmounted(() => {
        clearPolling();
    });

    return {
        waitingForCover,
        isExhausted,
        coverImageUrl,
    };
}
