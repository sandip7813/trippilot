<script setup lang="ts">
import {
    Backpack,
    Briefcase,
    CalendarDays,
    Globe,
    Heart,
    House,
    Landmark,
    Mountain,
    Plane,
    Route,
    Ship,
    Sun,
    User,
    UserPlus,
    Users,
    UsersRound,
} from '@lucide/vue';
import type { Component } from 'vue';
import TripBadge from '@/components/TripBadge.vue';
import type { TripBadgeTone } from '@/components/TripBadge.vue';
import type { TravelStyle, Trip, TripStatus } from '@/types/trip';

withDefaults(
    defineProps<{
        trip: Pick<
            Trip,
            | 'status'
            | 'status_label'
            | 'type'
            | 'type_label'
            | 'travel_style'
            | 'travel_style_label'
            | 'trip_scope'
            | 'trip_scope_label'
            | 'is_owner'
            | 'collaborator_role'
        >;
        size?: 'sm' | 'md';
        showScope?: boolean;
        showType?: boolean;
    }>(),
    { size: 'md', showScope: true, showType: true },
);

const statusTones: Record<TripStatus, TripBadgeTone> = {
    draft: 'amber',
    planned: 'emerald',
    archived: 'neutral',
};

const travelStyleIcons: Record<TravelStyle, Component> = {
    family: Users,
    business: Briefcase,
    adventure: Mountain,
    romantic: Heart,
    backpacking: Backpack,
    solo: User,
    group: UsersRound,
    pilgrimage: Landmark,
    cruise: Ship,
    weekend: CalendarDays,
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <TripBadge :tone="statusTones[trip.status]" :size="size" dot>
            {{ trip.status_label }}
        </TripBadge>
        <TripBadge
            v-if="showType"
            :icon="trip.type === 'road' ? Route : Plane"
            :size="size"
        >
            {{ trip.type_label }}
        </TripBadge>
        <TripBadge
            v-if="trip.travel_style && trip.travel_style_label"
            tone="violet"
            :icon="travelStyleIcons[trip.travel_style] ?? Sun"
            :size="size"
        >
            {{ trip.travel_style_label }}
        </TripBadge>
        <TripBadge
            v-if="showScope && trip.trip_scope_label"
            tone="sky"
            :icon="trip.trip_scope === 'international' ? Globe : House"
            :size="size"
        >
            {{ trip.trip_scope_label }}
        </TripBadge>
        <slot />
        <TripBadge
            v-if="!trip.is_owner && trip.collaborator_role"
            tone="teal"
            :icon="UserPlus"
            :size="size"
            class="capitalize"
        >
            Invited · {{ trip.collaborator_role }}
        </TripBadge>
    </div>
</template>
