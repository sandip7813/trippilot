<script setup lang="ts">
import type {
    DivIcon,
    LatLngExpression,
    LayerGroup,
    Map as LeafletMap,
} from 'leaflet';
import type * as LeafletTypes from 'leaflet';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import type { TripRouteMapPoint } from '@/types/trip';

type LeafletModule = typeof LeafletTypes;

const props = defineProps<{
    points: TripRouteMapPoint[];
}>();

const mapRoot = ref<HTMLElement | null>(null);
const mapReady = ref(false);

let leaflet: LeafletModule | null = null;
let map: LeafletMap | null = null;
let layerGroup: LayerGroup | null = null;
let resizeObserver: ResizeObserver | null = null;

function markerColor(kind: TripRouteMapPoint['kind']): string {
    if (kind === 'origin') {
        return '#0d9488';
    }

    if (kind === 'return') {
        return '#059669';
    }

    return '#6366f1';
}

function numberedPinIcon(L: LeafletModule, color: string, label: string): DivIcon {
    const width = 36;
    const height = 46;

    return L.divIcon({
        className: '',
        html: `<div style="position:relative;width:${width}px;height:${height}px;filter:drop-shadow(0 4px 10px rgba(15,23,42,.35));">
            <div style="position:absolute;left:50%;top:0;transform:translateX(-50%);width:32px;height:32px;border-radius:50%;background:${color};border:3px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font:700 13px/1 system-ui,sans-serif;">${label}</div>
            <div style="position:absolute;left:50%;top:28px;transform:translateX(-50%);width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-top:12px solid ${color};"></div>
        </div>`,
        iconSize: [width, height],
        iconAnchor: [width / 2, height],
    });
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderMap(): void {
    const L = leaflet;

    if (!L || !mapRoot.value) {
        return;
    }

    if (!map) {
        map = L.map(mapRoot.value, {
            scrollWheelZoom: true,
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

    const bounds: LatLngExpression[] = [];
    const linePoints: LatLngExpression[] = [];

    for (const point of props.points) {
        const position: LatLngExpression = [point.lat, point.lng];
        const last = linePoints[linePoints.length - 1] as
            | [number, number]
            | undefined;

        if (
            !last ||
            last[0] !== point.lat ||
            last[1] !== point.lng
        ) {
            linePoints.push(position);
        }

        const marker = L.marker(position, {
            icon: numberedPinIcon(L, markerColor(point.kind), String(point.sequence)),
            zIndexOffset: point.kind === 'origin' ? 1000 : 500,
        });

        marker.bindTooltip(
            `<div style="font:600 13px/1.3 system-ui,sans-serif">${escapeHtml(point.label)}</div>`,
            {
                direction: 'top',
                opacity: 1,
                offset: [0, -12],
            },
        );

        marker.addTo(layerGroup);
        bounds.push(position);
    }

    if (linePoints.length >= 2) {
        L.polyline(linePoints, {
            color: '#0d9488',
            weight: 4,
            opacity: 0.85,
            dashArray: props.points.length > 2 ? '8 10' : undefined,
        }).addTo(layerGroup);
    }

    if (bounds.length === 1) {
        map.setView(bounds[0], 11);
    } else if (bounds.length > 1) {
        map.fitBounds(L.latLngBounds(bounds), {
            padding: [48, 48],
            maxZoom: 12,
        });
    }

    mapReady.value = true;
    map.invalidateSize();
}

async function ensureLeaflet(): Promise<void> {
    if (leaflet !== null) {
        return;
    }

    await import('leaflet/dist/leaflet.css');
    leaflet = (await import('leaflet')).default;
}

onMounted(async () => {
    await ensureLeaflet();
    renderMap();

    if (mapRoot.value) {
        resizeObserver = new ResizeObserver(() => {
            map?.invalidateSize();
        });
        resizeObserver.observe(mapRoot.value);
    }
});

watch(
    () => props.points,
    () => {
        renderMap();
    },
    { deep: true },
);

onUnmounted(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
    map?.remove();
    map = null;
    layerGroup = null;
    leaflet = null;
});
</script>

<template>
    <div
        ref="mapRoot"
        class="size-full min-h-[50vh] bg-muted sm:min-h-[60vh]"
        :class="{ 'opacity-0': !mapReady }"
    />
</template>
