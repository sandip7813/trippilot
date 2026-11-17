import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CreditServiceKey } from '@/types/credits';

export function formatCredits(credits: number): string {
    return `${credits} ${Math.abs(credits) === 1 ? 'credit' : 'credits'}`;
}

/**
 * Credit balance and per-service costs for the signed-in user. Every helper
 * returns null / true when the user does not spend credits (admins, or
 * credits switched off), so callers can hide credit UI without extra checks.
 */
export function useCredits() {
    const page = usePage();

    const credits = computed(() => page.props.credits);

    function costOf(service: CreditServiceKey): number | null {
        return credits.value?.costs[service] ?? null;
    }

    function costLabel(service: CreditServiceKey): string | null {
        const cost = costOf(service);

        return cost === null || cost === 0 ? null : formatCredits(cost);
    }

    function canAfford(service: CreditServiceKey): boolean {
        const cost = costOf(service);

        return cost === null || (credits.value?.balance ?? 0) >= cost;
    }

    return { credits, costOf, costLabel, canAfford };
}
