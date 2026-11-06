<script setup lang="ts">
import { Phone } from '@lucide/vue';
import { computed, watch } from 'vue';
import IconField from '@/components/auth/IconField.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CountryDialCodeOption } from '@/types';

const props = defineProps<{
    countryDialCodes: CountryDialCodeOption[];
    error?: string;
    tabindex?: number;
    inputClass?: string;
}>();

const countryCode = defineModel<string>('countryCode', { required: true });
const mobileNumber = defineModel<string>('mobileNumber', { required: true });

const selectedDialCode = computed(
    () =>
        props.countryDialCodes.find(
            (dialCode) => dialCode.value === countryCode.value,
        ) ?? props.countryDialCodes[0],
);

const digitsHint = computed(() => {
    const { minDigits, maxDigits } = selectedDialCode.value;

    return minDigits === maxDigits
        ? `${maxDigits} digits`
        : `${minDigits}–${maxDigits} digits`;
});

/**
 * Keeps the mobile number to digits only (typed or pasted), capped at the
 * selected country's maximum length.
 */
watch(
    [mobileNumber, () => selectedDialCode.value.maxDigits],
    ([number, maxDigits]) => {
        const digits = number.replace(/\D/g, '').slice(0, maxDigits);

        if (digits !== number) {
            mobileNumber.value = digits;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="grid gap-2">
        <Label for="mobile_number">
            Mobile number
            <span class="font-normal text-muted-foreground">(optional)</span>
        </Label>
        <div class="flex gap-2">
            <select
                id="mobile_country_code"
                v-model="countryCode"
                name="mobile_country_code"
                :tabindex="tabindex"
                autocomplete="tel-country-code"
                aria-label="Country code"
                :class="[
                    'w-24 shrink-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30',
                    inputClass ?? 'h-9',
                ]"
            >
                <option
                    v-for="dialCode in countryDialCodes"
                    :key="dialCode.value"
                    :value="dialCode.value"
                    :title="dialCode.label"
                >
                    {{ dialCode.value }} {{ dialCode.label }}
                </option>
            </select>
            <IconField :icon="Phone" class="flex-1">
                <Input
                    id="mobile_number"
                    v-model="mobileNumber"
                    type="tel"
                    name="mobile_number"
                    inputmode="numeric"
                    :pattern="`[0-9]{${selectedDialCode.minDigits},${selectedDialCode.maxDigits}}`"
                    :maxlength="selectedDialCode.maxDigits"
                    :title="`Enter a ${digitsHint} mobile number`"
                    :tabindex="
                        tabindex === undefined ? undefined : tabindex + 1
                    "
                    autocomplete="tel-national"
                    :placeholder="digitsHint"
                    :class="['pl-10', inputClass ?? 'h-9']"
                />
            </IconField>
        </div>
        <InputError :message="error" />
    </div>
</template>
