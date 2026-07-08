<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="app-page-bg flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <div
                    class="brand-gradient flex size-10 items-center justify-center rounded-xl shadow-lg shadow-teal-500/25"
                >
                    <AppLogoIcon class="size-6 fill-current text-white" />
                </div>
            </Link>

            <div class="flex flex-col gap-6">
                <Card class="card-vibrant overflow-hidden rounded-2xl">
                    <div class="brand-gradient h-1.5" />
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl font-bold">
                            <span class="brand-gradient-text">{{ title }}</span>
                        </CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
