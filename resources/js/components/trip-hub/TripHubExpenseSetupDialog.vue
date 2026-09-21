<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';

type Row = { name: string; email: string; phone: string };

const props = defineProps<{
    tripId: string;
    suggested: { name: string; email: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send, firstError } = useExpenseRequest();
const rows = ref<Row[]>([]);

watch(open, (isOpen) => {
    if (isOpen) {
        rows.value = props.suggested.slice(0, 1).map((person) => ({
            name: person.name,
            email: person.email,
            phone: '',
        }));

        if (rows.value.length === 0) {
            rows.value = [{ name: '', email: '', phone: '' }];
        }
    }
});

const unusedSuggestions = computed(() =>
    props.suggested.filter(
        (person) =>
            !rows.value.some(
                (row) => row.email.toLowerCase() === person.email.toLowerCase(),
            ),
    ),
);

function addSuggested(person: { name: string; email: string }): void {
    rows.value.push({ name: person.name, email: person.email, phone: '' });
}

function submit(): void {
    send(
        'post',
        TripExpenseController.storeSheet.url({ trip: props.tripId }),
        { participants: rows.value },
        () => {
            open.value = false;
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Create the expense sheet</DialogTitle>
                <DialogDescription>
                    Add everyone who shares costs on this trip. They don't need
                    an account, but a name and email are required.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="unusedSuggestions.length > 0"
                class="flex flex-wrap items-center gap-2 text-sm"
            >
                <span class="text-muted-foreground">Already on this trip:</span>
                <Button
                    v-for="person in unusedSuggestions"
                    :key="person.email"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addSuggested(person)"
                >
                    <Plus class="size-3.5" />
                    {{ person.name }}
                </Button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="(row, index) in rows"
                    :key="index"
                    class="grid gap-2 rounded-lg border border-border/60 p-3 sm:grid-cols-[1fr_1fr_9rem_auto] sm:items-end"
                >
                    <div class="grid gap-1.5">
                        <Label :for="`setup-name-${index}`">Name</Label>
                        <Input :id="`setup-name-${index}`" v-model="row.name" />
                        <InputError
                            :message="errors[`participants.${index}.name`]"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`setup-email-${index}`">Email</Label>
                        <Input
                            :id="`setup-email-${index}`"
                            v-model="row.email"
                            type="email"
                        />
                        <InputError
                            :message="errors[`participants.${index}.email`]"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`setup-phone-${index}`">Phone</Label>
                        <Input
                            :id="`setup-phone-${index}`"
                            v-model="row.phone"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="rows.length === 1"
                        aria-label="Remove participant"
                        @click="rows.splice(index, 1)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                :disabled="rows.length >= 30"
                @click="rows.push({ name: '', email: '', phone: '' })"
            >
                <Plus class="size-4" />
                Add person
            </Button>

            <InputError :message="errors.expense ?? errors.participants" />

            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">
                    Cancel
                </Button>
                <Button type="button" :disabled="processing" @click="submit">
                    <Spinner v-if="processing" class="size-4" />
                    Create sheet
                </Button>
            </DialogFooter>
            <span class="sr-only">{{ firstError() }}</span>
        </DialogContent>
    </Dialog>
</template>
