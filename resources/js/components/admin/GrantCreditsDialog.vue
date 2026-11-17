<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Coins } from '@lucide/vue';
import { ref } from 'vue';
import UserCreditController from '@/actions/App/Http/Controllers/Admin/Super/UserCreditController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { AdminUser } from '@/types/admin';

const props = defineProps<{
    user: AdminUser;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="sm" variant="outline">
                <Coins class="mr-1.5 size-4" />
                Add credits
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Add credits to {{ props.user.name }}</DialogTitle>
                <DialogDescription>
                    Current balance: {{ props.user.credit_balance }} credits.
                    The user will see this in their credit history.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-bind="UserCreditController.store.form(props.user.id)"
                v-slot="{ errors, processing }"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                reset-on-success
                @success="open = false"
            >
                <div class="space-y-2">
                    <Label :for="`grant-credits-${props.user.id}`">
                        Credits to add
                    </Label>
                    <Input
                        :id="`grant-credits-${props.user.id}`"
                        name="credits"
                        type="number"
                        min="1"
                        max="10000"
                        step="1"
                        required
                    />
                    <InputError :message="errors.credits" />
                </div>

                <div class="space-y-2">
                    <Label :for="`grant-note-${props.user.id}`">
                        Note (optional)
                    </Label>
                    <Input
                        :id="`grant-note-${props.user.id}`"
                        name="note"
                        maxlength="255"
                        placeholder="e.g. Compensation for a failed trip plan"
                    />
                    <InputError :message="errors.note" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" class="mr-2" />
                        Add credits
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
