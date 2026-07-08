<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

defineProps<{
    editUrl: string;
}>();
</script>

<template>
    <Alert>
        <MapPin class="size-4" />
        <AlertTitle>Update destination for weather &amp; maps</AlertTitle>
        <AlertDescription
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <span>
                Re-select your destination from the place search so we can save
                map coordinates.
            </span>
            <Button variant="outline" size="sm" as-child class="shrink-0">
                <Link :href="editUrl">Edit destination</Link>
            </Button>
        </AlertDescription>
    </Alert>
</template>
