import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Method = 'post' | 'patch' | 'delete';

/**
 * Sends one expense mutation and only refreshes the `expenses` prop afterwards.
 * Validation and rule errors (the `expense` key) end up in `errors`.
 */
export function useExpenseRequest() {
    const processing = ref(false);
    const errors = ref<Record<string, string>>({});

    function send(
        method: Method,
        url: string,
        data: Record<string, unknown> = {},
        onSuccess?: () => void,
    ): void {
        processing.value = true;
        errors.value = {};

        router[method](url, data as never, {
            preserveScroll: true,
            only: ['expenses'],
            onSuccess: () => onSuccess?.(),
            onError: (received) => {
                errors.value = received as Record<string, string>;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    }

    function firstError(): string | null {
        return Object.values(errors.value)[0] ?? null;
    }

    return { processing, errors, send, firstError };
}
