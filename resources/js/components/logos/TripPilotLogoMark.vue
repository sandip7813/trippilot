<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import LogoCompass from '@/components/logos/LogoCompass.vue';
import LogoGlobe from '@/components/logos/LogoGlobe.vue';
import LogoMonogram from '@/components/logos/LogoMonogram.vue';
import LogoPin from '@/components/logos/LogoPin.vue';
import LogoPlane from '@/components/logos/LogoPlane.vue';
import { ACTIVE_LOGO } from '@/config/brand';
import type { LogoVariant } from '@/config/brand';

defineOptions({ inheritAttrs: false });

type Props = {
    className?: HTMLAttributes['class'];
    variant?: 'default' | 'light';
    logo?: LogoVariant;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

const page = usePage();

const logos: Record<LogoVariant, Component> = {
    plane: LogoPlane,
    compass: LogoCompass,
    pin: LogoPin,
    globe: LogoGlobe,
    monogram: LogoMonogram,
};

const activeLogo = computed((): LogoVariant => {
    if (props.logo) {
        return props.logo;
    }

    const shared = page.props.brand?.logo;

    if (shared && shared in logos) {
        return shared as LogoVariant;
    }

    return ACTIVE_LOGO;
});

const LogoComponent = computed(() => logos[activeLogo.value]);
</script>

<template>
    <component
        :is="LogoComponent"
        :class="className"
        :variant="variant"
        v-bind="$attrs"
    />
</template>
