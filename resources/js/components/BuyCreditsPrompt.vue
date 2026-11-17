<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Coins, ShoppingCart } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    creditServiceActions,
    useBuyCreditsPrompt,
} from '@/composables/useBuyCreditsPrompt';
import { formatCredits, useCredits } from '@/composables/useCredits';
import { buy as buyCredits } from '@/routes/credits';

const { prompt } = useBuyCreditsPrompt();
const { credits, costOf } = useCredits();

const required = computed(() =>
    prompt.service ? (costOf(prompt.service) ?? 0) : 0,
);

const action = computed(() =>
    prompt.service ? creditServiceActions[prompt.service] : 'This AI feature',
);

function goToBuyCredits(): void {
    prompt.open = false;
    router.visit(buyCredits());
}
</script>

<template>
    <Dialog v-model:open="prompt.open">
        <DialogContent class="sm:max-w-md" data-test="buy-credits-prompt">
            <DialogHeader>
                <div
                    class="mb-2 flex size-11 items-center justify-center rounded-xl bg-linear-to-br from-amber-500 to-orange-500 text-white shadow-md shadow-amber-500/30"
                >
                    <Coins class="size-5" />
                </div>
                <DialogTitle>You need more credits</DialogTitle>
                <DialogDescription>
                    {{ action }} uses
                    <strong>{{ formatCredits(required) }}</strong
                    >, and you have
                    <strong>{{ formatCredits(credits?.balance ?? 0) }}</strong
                    >. Would you like to buy credits now?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="outline">Not now</Button>
                </DialogClose>
                <Button type="button" @click="goToBuyCredits">
                    <ShoppingCart class="mr-2 size-4" />
                    Buy credits
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
