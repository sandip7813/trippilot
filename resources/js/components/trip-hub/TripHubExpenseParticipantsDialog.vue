<script setup lang="ts">
import { Check, Pencil, Trash2, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import TripExpenseController from '@/actions/App/Http/Controllers/TripExpenseController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseRequest } from '@/composables/useExpenseRequest';
import type { ExpenseParticipant } from '@/types/expenses';

const props = defineProps<{
    tripId: string;
    participants: ExpenseParticipant[];
    suggested: { name: string; email: string }[];
    editable: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const { processing, errors, send } = useExpenseRequest();

const form = ref({ name: '', email: '', phone: '' });
const editingId = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
});

function resetForm(): void {
    form.value = { name: '', email: '', phone: '' };
    editingId.value = null;
}

function startEdit(participant: ExpenseParticipant): void {
    editingId.value = participant.id;
    form.value = {
        name: participant.name,
        email: participant.email,
        phone: participant.phone ?? '',
    };
}

function submit(): void {
    const url =
        editingId.value === null
            ? TripExpenseController.storeParticipant.url({ trip: props.tripId })
            : TripExpenseController.updateParticipant.url({
                  trip: props.tripId,
                  participantId: editingId.value,
              });

    send(
        editingId.value === null ? 'post' : 'patch',
        url,
        { ...form.value },
        resetForm,
    );
}

function remove(participant: ExpenseParticipant): void {
    send(
        'delete',
        TripExpenseController.destroyParticipant.url({
            trip: props.tripId,
            participantId: participant.id,
        }),
    );
}

function addSuggested(person: { name: string; email: string }): void {
    send(
        'post',
        TripExpenseController.storeParticipant.url({ trip: props.tripId }),
        { name: person.name, email: person.email, phone: '' },
    );
}

function unusedSuggestions(): { name: string; email: string }[] {
    return props.suggested.filter(
        (person) =>
            !props.participants.some(
                (participant) =>
                    participant.email.toLowerCase() ===
                    person.email.toLowerCase(),
            ),
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Participants</DialogTitle>
                <DialogDescription>
                    People who pay for or share costs on this trip. People who
                    are already part of an entry are archived instead of
                    removed.
                </DialogDescription>
            </DialogHeader>

            <ul class="divide-y divide-border/60 rounded-lg border">
                <li
                    v-for="participant in participants"
                    :key="participant.id"
                    class="flex items-center justify-between gap-3 px-3 py-2"
                >
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 font-medium">
                            <span class="truncate">{{ participant.name }}</span>
                            <Badge
                                v-if="participant.archived"
                                variant="outline"
                            >
                                Archived
                            </Badge>
                            <Badge
                                v-else-if="participant.is_portal_user"
                                variant="secondary"
                            >
                                Member
                            </Badge>
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ participant.email }}
                            <template v-if="participant.phone">
                                · {{ participant.phone }}
                            </template>
                        </p>
                    </div>
                    <div
                        v-if="editable && !participant.archived"
                        class="flex shrink-0 gap-1"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Edit participant"
                            @click="startEdit(participant)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Remove participant"
                            :disabled="processing"
                            @click="remove(participant)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </li>
            </ul>

            <template v-if="editable">
                <div
                    v-if="unusedSuggestions().length > 0 && editingId === null"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="text-muted-foreground">Add from trip:</span>
                    <Button
                        v-for="person in unusedSuggestions()"
                        :key="person.email"
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        @click="addSuggested(person)"
                    >
                        {{ person.name }}
                    </Button>
                </div>

                <form
                    class="grid gap-3 rounded-lg border border-border/60 p-3 sm:grid-cols-3"
                    @submit.prevent="submit"
                >
                    <p class="text-sm font-semibold sm:col-span-3">
                        {{ editingId === null ? 'Add someone' : 'Edit person' }}
                    </p>
                    <div class="grid gap-1.5">
                        <Label for="participant-name">Name</Label>
                        <Input id="participant-name" v-model="form.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="participant-email">Email</Label>
                        <Input
                            id="participant-email"
                            v-model="form.email"
                            type="email"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="participant-phone">Phone (optional)</Label>
                        <Input id="participant-phone" v-model="form.phone" />
                    </div>
                    <InputError
                        class="sm:col-span-3"
                        :message="errors.expense"
                    />
                    <div class="flex gap-2 sm:col-span-3">
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" class="size-4" />
                            <Check v-else class="size-4" />
                            {{ editingId === null ? 'Add' : 'Save' }}
                        </Button>
                        <Button
                            v-if="editingId !== null"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="resetForm"
                        >
                            <X class="size-4" />
                            Cancel
                        </Button>
                    </div>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
