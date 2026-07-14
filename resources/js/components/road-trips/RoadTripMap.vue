<script setup lang="ts">
import type {
    DivIcon,
    LatLngExpression,
    LayerGroup,
    Map as LeafletMap,
    Marker,
    Polyline,
} from 'leaflet';
import type * as LeafletTypes from 'leaflet';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import {
    amenityLayerStyle,
    amenityPlaceKey,
    amenityRouteZoneLabel,
    locationCoordinates,
    stopDisplayAddress,
} from '@/types/roadTrip';
import type {
    AmenityLayerStyle,
    RoadTripBreak,
    RoadTripPlace,
    RoadTripRoute,
    RoadTripStop,
} from '@/types/roadTrip';
import type { TripLocation, TripRouteMapPoint } from '@/types/trip';

type LeafletModule = typeof LeafletTypes;

const props = defineProps<{
    origin: TripLocation | null;
    destination: TripLocation | null;
    route: RoadTripRoute | null;
    cityPoints?: TripRouteMapPoint[];
    stops?: RoadTripStop[];
    suggestedBreaks?: RoadTripBreak[];
    amenityPlaces?: RoadTripPlace[];
    activeAmenityLayer?: string | null;
    focusedAmenityPlaceKey?: string | null;
    class?: string;
}>();

const mapRoot = ref<HTMLElement | null>(null);
const mapReady = ref(false);

let leaflet: LeafletModule | null = null;
let map: LeafletMap | null = null;
let layerGroup: LayerGroup | null = null;
let resizeObserver: ResizeObserver | null = null;

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function displayMetaLabel(meta?: string | null): string | null {
    const trimmed = meta?.trim();

    if (!trimmed || trimmed === 'Suggested stop along your route.') {
        return null;
    }

    return trimmed;
}

function markerInfoHtml(
    title: string,
    address?: string | null,
    meta?: string | null,
): string {
    const lines = [
        `<div class="road-trip-map-info__title">${escapeHtml(title)}</div>`,
    ];

    if (address?.trim()) {
        lines.push(
            `<div class="road-trip-map-info__address">${escapeHtml(address.trim())}</div>`,
        );
    }

    const displayMeta = displayMetaLabel(meta);

    if (displayMeta && displayMeta !== address?.trim()) {
        lines.push(
            `<div class="road-trip-map-info__meta">${escapeHtml(displayMeta)}</div>`,
        );
    }

    return `<div class="road-trip-map-info__content">${lines.join('')}</div>`;
}

function bindMarkerInfo(
    marker: ReturnType<LeafletModule['marker']>,
    html: string,
): void {
    marker.bindTooltip(html, {
        direction: 'top',
        opacity: 1,
        interactive: true,
        className: 'road-trip-map-tooltip',
        offset: [0, -12],
    });

    let closeTimer: ReturnType<typeof setTimeout> | null = null;

    const cancelClose = (): void => {
        if (closeTimer !== null) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
    };

    const scheduleClose = (): void => {
        cancelClose();
        closeTimer = setTimeout(() => {
            marker.closeTooltip();
            closeTimer = null;
        }, 600);
    };

    marker.on('mouseout', scheduleClose);

    marker.on('tooltipopen', () => {
        const element = marker.getTooltip()?.getElement();

        if (!element) {
            return;
        }

        element.addEventListener('mouseenter', () => {
            cancelClose();
            map?.dragging.disable();
        });

        element.addEventListener('mouseleave', () => {
            map?.dragging.enable();
            scheduleClose();
        });
    });

    marker.on('tooltipclose', () => {
        map?.dragging.enable();
    });
}

function circleIcon(L: LeafletModule, color: string, size = 12): DivIcon {
    return L.divIcon({
        className: '',
        html: `<span style="display:block;width:${size}px;height:${size}px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,.35)"></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
    });
}

function numberedPinIcon(
    L: LeafletModule,
    color: string,
    label: string,
): DivIcon {
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

function cityMarkerColor(kind: TripRouteMapPoint['kind']): string {
    if (kind === 'origin') {
        return '#0d9488';
    }

    if (kind === 'return') {
        return '#059669';
    }

    return '#6366f1';
}

function endpointPinIcon(
    L: LeafletModule,
    color: string,
    label: string,
): DivIcon {
    const width = 40;
    const height = 50;

    return L.divIcon({
        className: '',
        html: `<div style="position:relative;width:${width}px;height:${height}px;filter:drop-shadow(0 4px 10px rgba(15,23,42,.35));">
            <div style="position:absolute;left:50%;top:0;transform:translateX(-50%);width:36px;height:36px;border-radius:50%;background:${color};border:3px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font:700 15px/1 system-ui,sans-serif;letter-spacing:-0.02em;">${label}</div>
            <div style="position:absolute;left:50%;top:30px;transform:translateX(-50%);width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-top:14px solid ${color};"></div>
        </div>`,
        iconSize: [width, height],
        iconAnchor: [width / 2, height],
    });
}

function amenityIcon(L: LeafletModule, style: AmenityLayerStyle): DivIcon {
    return L.divIcon({
        className: '',
        html: `<span style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:9999px;background:${style.color};color:#fff;font-size:15px;line-height:1;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35)">${style.glyph}</span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

function highlightedAmenityIcon(
    L: LeafletModule,
    style: AmenityLayerStyle,
): DivIcon {
    return L.divIcon({
        className: '',
        html: `<span style="display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9999px;background:${style.color};color:#fff;font-size:17px;line-height:1;border:3px solid #fff;box-shadow:0 0 0 4px ${style.color}66,0 4px 14px rgba(0,0,0,.4)">${style.glyph}</span>`,
        iconSize: [38, 38],
        iconAnchor: [19, 19],
    });
}

function focusAmenityMarker(focusedMarker: Marker): void {
    if (!map) {
        return;
    }

    map.setView(focusedMarker.getLatLng(), 17, { animate: true });

    nextTick(() => {
        focusedMarker.openTooltip();
    });
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
    const amenityStyle = amenityLayerStyle(props.activeAmenityLayer);
    const amenityMarkers = new Map<string, Marker>();
    const cityPoints = props.cityPoints ?? [];

    if (cityPoints.length > 0) {
        cityPoints.forEach((point, index) => {
            if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) {
                return;
            }

            const markerPoint: LatLngExpression = [point.lat, point.lng];
            const cityMarker = L.marker(markerPoint, {
                icon: numberedPinIcon(
                    L,
                    cityMarkerColor(point.kind),
                    String(index + 1),
                ),
                zIndexOffset: 1000,
            });
            bindMarkerInfo(cityMarker, markerInfoHtml(point.label));
            cityMarker.addTo(layerGroup);
            bounds.push(markerPoint);
        });
    } else {
        const origin = locationCoordinates(props.origin);
        const destination = locationCoordinates(props.destination);

        if (origin) {
            const originMarker = L.marker(origin, {
                icon: endpointPinIcon(L, '#0d9488', 'A'),
                zIndexOffset: 1000,
            });
            bindMarkerInfo(
                originMarker,
                markerInfoHtml(props.origin?.label ?? 'Origin'),
            );
            originMarker.addTo(layerGroup);
            bounds.push(origin);
        }

        if (destination) {
            const destinationMarker = L.marker(destination, {
                icon: endpointPinIcon(L, '#dc2626', 'B'),
                zIndexOffset: 1000,
            });
            bindMarkerInfo(
                destinationMarker,
                markerInfoHtml(props.destination?.label ?? 'Destination'),
            );
            destinationMarker.addTo(layerGroup);
            bounds.push(destination);
        }
    }

    for (const stop of props.stops ?? []) {
        const point: LatLngExpression = [stop.lat, stop.lng];
        const stopMarker = L.marker(point, {
            icon: circleIcon(L, '#2563eb', 14),
        });
        bindMarkerInfo(
            stopMarker,
            markerInfoHtml(stop.label, stopDisplayAddress(stop), stop.notes),
        );
        stopMarker.addTo(layerGroup);
        bounds.push(point);
    }

    for (const breakPoint of props.suggestedBreaks ?? []) {
        const point: LatLngExpression = [breakPoint.lat, breakPoint.lng];
        const breakMarker = L.marker(point, {
            icon: circleIcon(L, '#f59e0b', 13),
        });
        bindMarkerInfo(
            breakMarker,
            markerInfoHtml(
                breakPoint.title,
                breakPoint.address,
                breakPoint.reason,
            ),
        );
        breakMarker.addTo(layerGroup);
        bounds.push(point);
    }

    const amenityPoints: LatLngExpression[] = [];

    for (const place of props.amenityPlaces ?? []) {
        if (!Number.isFinite(place.lat) || !Number.isFinite(place.lng)) {
            continue;
        }

        const point: LatLngExpression = [place.lat, place.lng];
        const key = amenityPlaceKey(place);
        const isFocused = key === props.focusedAmenityPlaceKey;
        const zoneLabel = amenityRouteZoneLabel(place.route_zone);
        const amenityMarker = L.marker(point, {
            icon: isFocused
                ? highlightedAmenityIcon(L, amenityStyle)
                : amenityIcon(L, amenityStyle),
            zIndexOffset: isFocused ? 2500 : 500,
        });
        bindMarkerInfo(
            amenityMarker,
            markerInfoHtml(place.name, place.address, zoneLabel),
        );
        amenityMarker.addTo(layerGroup);
        amenityMarkers.set(key, amenityMarker);
        amenityPoints.push(point);
    }

    const polyline = props.route?.polyline ?? [];
    let routeLine: Polyline | null = null;

    if (polyline.length >= 2) {
        routeLine = L.polyline(polyline, {
            color: '#0d9488',
            weight: 4,
            opacity: 0.85,
        }).addTo(layerGroup);
    }

    const focusedMarker = props.focusedAmenityPlaceKey
        ? amenityMarkers.get(props.focusedAmenityPlaceKey)
        : undefined;

    if (focusedMarker) {
        focusAmenityMarker(focusedMarker);
    } else if (amenityPoints.length > 0) {
        const focusBounds = L.latLngBounds(amenityPoints);

        if (routeLine) {
            focusBounds.extend(routeLine.getBounds());
        }

        map.fitBounds(focusBounds, { padding: [48, 48], maxZoom: 13 });
    } else if (routeLine) {
        map.fitBounds(routeLine.getBounds(), { padding: [32, 32] });
    } else if (bounds.length > 0) {
        map.fitBounds(L.latLngBounds(bounds), { padding: [32, 32] });
    } else {
        map.setView([20.5937, 78.9629], 5);
    }

    nextTick(() => {
        map?.invalidateSize();
    });
}

async function initializeMap(): Promise<void> {
    if (leaflet !== null) {
        return;
    }

    await import('leaflet/dist/leaflet.css');
    leaflet = (await import('leaflet')).default;
    mapReady.value = true;
    renderMap();
}

onMounted(() => {
    void initializeMap();

    if (typeof ResizeObserver === 'undefined' || !mapRoot.value) {
        return;
    }

    resizeObserver = new ResizeObserver(() => {
        map?.invalidateSize();
    });
    resizeObserver.observe(mapRoot.value);
});

onUnmounted(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
    map?.remove();
    map = null;
    layerGroup = null;
    leaflet = null;
});

watch(
    () => [
        props.origin,
        props.destination,
        props.cityPoints,
        props.route,
        props.stops,
        props.suggestedBreaks,
        props.amenityPlaces,
        props.activeAmenityLayer,
        props.focusedAmenityPlaceKey,
        mapReady.value,
    ],
    () => {
        if (mapReady.value) {
            renderMap();
        }
    },
    { deep: true },
);
</script>

<template>
    <div
        ref="mapRoot"
        :class="
            cn(
                'relative h-[420px] w-full overflow-hidden bg-muted/20 sm:h-[480px] lg:h-[540px]',
                props.class,
            )
        "
        role="img"
        aria-label="Road trip map"
    >
        <div
            v-if="!mapReady"
            class="absolute inset-0 flex items-center justify-center bg-muted/40 text-sm text-muted-foreground"
        >
            Loading map...
        </div>
    </div>
</template>

<style>
.leaflet-tooltip.road-trip-map-tooltip {
    box-sizing: border-box;
    width: 260px;
    min-width: 260px;
    max-width: 260px;
    padding: 10px 12px;
    border: 1px solid rgb(226 232 240);
    border-radius: 10px;
    background: rgb(255 255 255 / 0.98);
    box-shadow:
        0 4px 12px rgb(15 23 42 / 0.12),
        0 1px 2px rgb(15 23 42 / 0.06);
    color: rgb(15 23 42);
    white-space: normal !important;
    pointer-events: auto;
    user-select: text;
    -webkit-user-select: text;
    cursor: text;
}

.dark .leaflet-tooltip.road-trip-map-tooltip {
    border-color: rgb(51 65 85);
    background: rgb(15 23 42 / 0.96);
    color: rgb(248 250 252);
}

.leaflet-tooltip.road-trip-map-tooltip::before {
    border-top-color: rgb(255 255 255 / 0.98);
}

.dark .leaflet-tooltip.road-trip-map-tooltip::before {
    border-top-color: rgb(15 23 42 / 0.96);
}

.road-trip-map-info__content {
    display: block;
    width: 100%;
}

.road-trip-map-info__title {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.35;
    overflow-wrap: break-word;
}

.road-trip-map-info__address {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.45;
    color: rgb(71 85 105);
    overflow-wrap: break-word;
}

.dark .road-trip-map-info__address {
    color: rgb(203 213 225);
}

.road-trip-map-info__meta {
    margin-top: 6px;
    font-size: 11px;
    line-height: 1.35;
    color: rgb(100 116 139);
    overflow-wrap: break-word;
}

.dark .road-trip-map-info__meta {
    color: rgb(148 163 184);
}
</style>
