<script setup lang="ts">
import { computed, ref } from 'vue';
import DatePickerField from '@/components/DatePickerField.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { addDaysToIso, isoToday } from '@/lib/dates';
import type { OpenTripDetails } from '@/types/trip';

const {
    details,
    errors,
    namePrefix = '',
    tripStartDate,
} = defineProps<{
    details?: OpenTripDetails | null;
    errors: Record<string, string>;
    /**
     * When set (e.g. "open_trip"), fields submit as a nested array
     * (`open_trip[category]`) and errors are read from the matching
     * dot-notation key (`open_trip.category`) — used on the create form,
     * where these fields ride along with the main trip submission. Left
     * empty on the edit page, where they submit to their own endpoint with
     * flat field names.
     */
    namePrefix?: string;
    /**
     * The trip's start date (ISO), when known, so the join deadline can be
     * capped to 3 days before it starts.
     */
    tripStartDate?: string | null;
}>();

function fieldName(name: string): string {
    return namePrefix ? `${namePrefix}[${name}]` : name;
}

function errorKey(name: string): string {
    return namePrefix ? `${namePrefix}.${name}` : name;
}

// Native <select>/<textarea> elements need local, uncontrolled state: a
// reactive `:value` binding on a plain DOM element gets re-applied on every
// re-render of this component (e.g. when a sibling field's error changes),
// which would otherwise keep snapping the field back to `details?.x ?? ''`
// and silently discard whatever the user picked. Only initialized once here,
// same pattern already used for join deadline. The Input component (used for
// the other text fields below) already handles this internally via its own
// `default-value` prop.
const category = ref(details?.category ?? '');
const difficulty = ref(details?.difficulty ?? '');
const requirements = ref(details?.requirements ?? '');
const rules = ref(details?.rules ?? '');
const costModel = ref(details?.cost_model ?? '');
const costInclusions = ref(details?.cost_inclusions ?? '');

const joinDeadlineIso = ref(details?.join_deadline?.slice(0, 10) ?? '');

const joinDeadlineMin = computed(() => isoToday());

const joinDeadlineMax = computed((): string | undefined => {
    if (!tripStartDate) {
        return undefined;
    }

    return addDaysToIso(tripStartDate, -3) ?? undefined;
});

const joinDeadlineDisabled = computed(
    () =>
        joinDeadlineMax.value !== undefined &&
        joinDeadlineMax.value < joinDeadlineMin.value,
);
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Category, max group size, and cost model are required before you can
            publish. Everything else is optional.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="fieldName('category')">Category *</Label>
                <select
                    :id="fieldName('category')"
                    :name="fieldName('category')"
                    required
                    v-model="category"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Select a category</option>
                    <option value="trek">Trek</option>
                    <option value="bike">Bike</option>
                    <option value="road_trip">Road trip</option>
                    <option value="vacation">Vacation</option>
                    <option value="adventure">Adventure</option>
                    <option value="leisure">Leisure</option>
                    <option value="spiritual">Spiritual</option>
                    <option value="cultural">Cultural</option>
                    <option value="wildlife">Wildlife</option>
                    <option value="backpacking">Backpacking</option>
                    <option value="weekend_getaway">Weekend getaway</option>
                    <option value="other">Other</option>
                </select>
                <InputError :message="errors[errorKey('category')]" />
            </div>

            <div class="grid gap-2">
                <Label :for="fieldName('difficulty')">Difficulty</Label>
                <select
                    :id="fieldName('difficulty')"
                    :name="fieldName('difficulty')"
                    v-model="difficulty"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Select difficulty (optional)</option>
                    <option value="easy">Easy</option>
                    <option value="moderate">Moderate</option>
                    <option value="challenging">Challenging</option>
                </select>
                <InputError :message="errors[errorKey('difficulty')]" />
            </div>

            <div class="grid gap-2">
                <Label :for="fieldName('max_group_size')"
                    >Max group size *</Label
                >
                <Input
                    :id="fieldName('max_group_size')"
                    :name="fieldName('max_group_size')"
                    type="number"
                    min="1"
                    required
                    :default-value="details?.max_group_size ?? undefined"
                />
                <InputError :message="errors[errorKey('max_group_size')]" />
            </div>

            <div class="grid gap-2">
                <Label
                    :id="`${fieldName('join_deadline')}-label`"
                    :for="fieldName('join_deadline')"
                >
                    Join deadline
                </Label>
                <input
                    type="hidden"
                    :name="fieldName('join_deadline')"
                    :value="joinDeadlineIso"
                />
                <DatePickerField
                    :id="fieldName('join_deadline')"
                    v-model="joinDeadlineIso"
                    :min="joinDeadlineMin"
                    :max="joinDeadlineMax"
                    :disabled="joinDeadlineDisabled"
                />
                <p class="text-xs text-muted-foreground">
                    <template v-if="joinDeadlineDisabled">
                        The trip starts too soon for a join deadline (it needs
                        to be at least 3 days before the start date).
                    </template>
                    <template v-else>
                        Between today{{
                            tripStartDate
                                ? ' and 3 days before the trip starts'
                                : ''
                        }}.
                    </template>
                </p>
                <InputError :message="errors[errorKey('join_deadline')]" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="fieldName('requirements')">Requirements</Label>
            <textarea
                :id="fieldName('requirements')"
                :name="fieldName('requirements')"
                rows="2"
                v-model="requirements"
                placeholder="Basic fitness, own gear, valid ID..."
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="errors[errorKey('requirements')]" />
        </div>

        <div class="grid gap-2">
            <Label :for="fieldName('meeting_point')">Meeting point</Label>
            <Input
                :id="fieldName('meeting_point')"
                :name="fieldName('meeting_point')"
                :default-value="details?.meeting_point ?? ''"
            />
            <InputError :message="errors[errorKey('meeting_point')]" />
        </div>

        <div class="grid gap-2">
            <Label :for="fieldName('rules')">Group rules</Label>
            <textarea
                :id="fieldName('rules')"
                :name="fieldName('rules')"
                rows="2"
                v-model="rules"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="errors[errorKey('rules')]" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label :for="fieldName('cost_model')">Cost model</Label>
                <select
                    :id="fieldName('cost_model')"
                    :name="fieldName('cost_model')"
                    v-model="costModel"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Not set</option>
                    <option value="cost_sharing">
                        Cost sharing (estimate)
                    </option>
                    <option value="fixed_price">Fixed price</option>
                    <option value="pay_own">Everyone pays their own</option>
                </select>
                <InputError :message="errors[errorKey('cost_model')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="fieldName('cost_amount')">Amount / person</Label>
                <Input
                    :id="fieldName('cost_amount')"
                    :name="fieldName('cost_amount')"
                    type="number"
                    min="0"
                    step="0.01"
                    :default-value="details?.cost_amount ?? undefined"
                />
                <InputError :message="errors[errorKey('cost_amount')]" />
            </div>
            <div class="grid gap-2">
                <Label :for="fieldName('cost_currency')">Currency</Label>
                <Input
                    :id="fieldName('cost_currency')"
                    :name="fieldName('cost_currency')"
                    maxlength="3"
                    placeholder="INR"
                    :default-value="details?.cost_currency ?? 'INR'"
                />
                <InputError :message="errors[errorKey('cost_currency')]" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="fieldName('cost_inclusions')">What's included</Label>
            <textarea
                :id="fieldName('cost_inclusions')"
                :name="fieldName('cost_inclusions')"
                rows="2"
                v-model="costInclusions"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError :message="errors[errorKey('cost_inclusions')]" />
        </div>

        <div class="space-y-2">
            <Label class="flex items-center gap-2 font-normal">
                <!--
                    A checkbox alone sends nothing at all when unchecked, so
                    unchecking it would silently leave the old value in
                    place. The hidden "0" (submitted first, so an actual
                    check overrides it) and the checkbox's own value="1"
                    (rather than the browser default "on", which the
                    `boolean` validation rule doesn't accept) ensure this
                    field is always present and always a value Laravel
                    considers boolean.
                -->
                <input
                    type="hidden"
                    :name="fieldName('share_itinerary_with_members')"
                    value="0"
                />
                <Checkbox
                    :name="fieldName('share_itinerary_with_members')"
                    value="1"
                    :default-checked="
                        details?.share_itinerary_with_members ?? true
                    "
                />
                Share the full itinerary with accepted members
            </Label>
            <Label class="flex items-center gap-2 font-normal">
                <input
                    type="hidden"
                    :name="fieldName('member_names_visible')"
                    value="0"
                />
                <Checkbox
                    :name="fieldName('member_names_visible')"
                    value="1"
                    :default-checked="details?.member_names_visible ?? false"
                />
                Let members see each other's names
            </Label>
        </div>
    </div>
</template>
