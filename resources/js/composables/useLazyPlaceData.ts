import type { Ref } from 'vue';
import { ref } from 'vue';

export type LazyPlaceState<T> = {
    data: T | null;
    loading: boolean;
    error: string | null;
};

/**
 * On-demand data for trip hub tabs that switch between the places a trip
 * stays in (or between options within a place, such as its airports). The
 * server sends the default entry with the page; any other entry is fetched
 * from a JSON endpoint the first time it is opened, then kept.
 *
 * Entries are identified by a string key the caller chooses; `url` turns a
 * key into the endpoint to fetch it from.
 */
export function useLazyPlaceData<T>(
    url: (key: string) => string,
    errorMessage: string,
) {
    const states = ref({}) as Ref<Record<string, LazyPlaceState<T>>>;

    function reset(initial: Record<string, T | null>): void {
        states.value = Object.fromEntries(
            Object.entries(initial).map(([key, data]) => [
                key,
                { data, loading: false, error: null },
            ]),
        );
    }

    function stateFor(key: string): LazyPlaceState<T> {
        states.value[key] ??= { data: null, loading: false, error: null };

        return states.value[key];
    }

    async function load(key: string): Promise<void> {
        const state = stateFor(key);

        if (state.loading) {
            return;
        }

        state.loading = true;
        state.error = null;

        try {
            const response = await fetch(url(key), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(errorMessage);
            }

            state.data = (await response.json()) as T;
        } catch {
            state.error = errorMessage;
        } finally {
            state.loading = false;
        }
    }

    function ensureLoaded(key: string): void {
        const state = stateFor(key);

        if (state.data === null && !state.loading && !state.error) {
            void load(key);
        }
    }

    return { states, reset, load, ensureLoaded };
}
