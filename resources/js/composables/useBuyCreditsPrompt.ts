import { reactive } from 'vue';
import { useCredits } from '@/composables/useCredits';
import type { CreditServiceKey } from '@/types/credits';

/**
 * One shared prompt for the whole app, rendered once by BuyCreditsPrompt in
 * the app layout.
 */
const prompt = reactive<{ open: boolean; service: CreditServiceKey | null }>({
    open: false,
    service: null,
});

export const creditServiceActions: Record<CreditServiceKey, string> = {
    itinerary_generation: 'Generating an itinerary',
    itinerary_regeneration: 'Regenerating an itinerary',
    trip_chat_message: 'Each trip chat message',
    assistant_message: 'Each travel assistant message',
    road_break_suggestions: 'Suggesting breaks with AI',
};

/**
 * Lets AI buttons stay clickable for users without enough credits: the click
 * opens a "Buy credits?" prompt instead of running the AI action.
 */
export function useBuyCreditsPrompt() {
    const { canAfford } = useCredits();

    /**
     * Returns true when the user can pay for the service. Otherwise opens
     * the buy prompt and returns false.
     */
    function ensureCredits(service: CreditServiceKey): boolean {
        if (canAfford(service)) {
            return true;
        }

        prompt.service = service;
        prompt.open = true;

        return false;
    }

    /**
     * Click handler for submit buttons: stops the form from submitting when
     * the user cannot pay and shows the prompt instead.
     */
    function guardSubmit(service: CreditServiceKey, event: Event): void {
        if (!ensureCredits(service)) {
            event.preventDefault();
        }
    }

    return { prompt, ensureCredits, guardSubmit };
}
