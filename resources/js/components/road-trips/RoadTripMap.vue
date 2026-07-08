<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import type {
    RoadTripBreak,
    RoadTripPlace,
    RoadTripRoute,
    RoadTripStop,
} from '@/types/roadTrip';
import { locationCoordinates } from '@/types/roadTrip';
import type { TripLocation } from '@/types/trip';

const props = defineProps<{
    origin: TripLocation | null;
    destination: TripLocation | null;
    route: RoadTripRoute | null;
    stops?: RoadTripStop[];
    suggestedBreaks?: RoadTripBreak[];
    amenityPlaces?: RoadTripPlace[];
    amenityColor?: string;
}>();

const mapRoot = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let layerGroup: L.LayerGroup | null = null;

function circleIcon(color: string, size = 12): L.DivIcon {
    return L.divIcon({
        className: '',
        html: `<span style="display:block;width:${size}px;height:${size}px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,.35)"></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}

function renderMap(): void {
    if (!mapRoot.value) {
        return;
    }

    if (!map) {
        map = L.map(mapRoot.value, {
            scrollWheelZoom: false,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
    }

    if (layerGroup) {
        layerGroup.clearLayers();
    } else {
        layerGroup = L.layerGroup().addTo(map);
    }

    const bounds: L.LatLngExpression[] = [];

    const origin = locationCoordinates(props.origin);
    const destination = locationCoordinates(props.destination);

    if (origin) {
        L.marker(origin, { icon: circleIcon('#0d9488', 14) })
            .bindTooltip(props.origin?.label ?? 'Origin')
            .addTo(layerGroup);
        bounds.push(origin);
    }

    if (destination) {
        L.marker(destination, { icon: circleIcon('#dc2626', 14) })
            .bindTooltip(props.destination?.label ?? 'Destination')
            .addTo(layerGroup);
        bounds.push(destination);
    }

    for (const stop of props.stops ?? []) {
        const point: L.LatLngExpression = [stop.lat, stop.lng];
        L.marker(point, { icon: circleIcon('#2563eb', 11) })
            .bindTooltip(stop.label)
            .addTo(layerGroup);
        bounds.push(point);
    }

    for (const breakPoint of props.suggestedBreaks ?? []) {
        const point: L.LatLngExpression = [breakPoint.lat, breakPoint.lng];
        L.marker(point, { icon: circleIcon('#f59e0b', 10) })
            .bindTooltip(breakPoint.title)
            .addTo(layerGroup);
        bounds.push(point);
    }

    for (const place of props.amenityPlaces ?? []) {
        const point: L.LatLngExpression = [place.lat, place.lng];
        L.marker(point, {
            icon: circleIcon(props.amenityColor ?? '#64748b', 8),
        })
            .bindTooltip(place.name)
            .addTo(layerGroup);
        bounds.push(point);
    }

    const polyline = props.route?.polyline ?? [];

    if (polyline.length >= 2) {
        const line = L.polyline(polyline, {
            color: '#0d9488',
            weight: 4,
            opacity: 0.85,
        }).addTo(layerGroup);

        bounds.push(...polyline);
        map.fitBounds(line.getBounds(), { padding: [24, 24] });
    } else if (bounds.length > 0) {
        map.fitBounds(L.latLngBounds(bounds), { padding: [24, 24] });
    } else {
        map.setView([20.5937, 78.9629], 5);
    }
}

onMounted(() => {
    renderMap();
});

onUnmounted(() => {
    map?.remove();
    map = null;
    layerGroup = null;
});

watch(
    () => [
        props.origin,
        props.destination,
        props.route,
        props.stops,
        props.suggestedBreaks,
        props.amenityPlaces,
    ],
    () => {
        renderMap();
    },
    { deep: true },
);
</script>

<template>
    <div
        ref="mapRoot"
        class="h-72 w-full rounded-xl border border-border/60 bg-muted/30 sm:h-96"
        role="img"
        aria-label="Road trip map"
    />
</template>
