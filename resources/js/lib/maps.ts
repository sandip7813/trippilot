/**
 * OpenStreetMap embed URL for an inline map preview.
 */
export function openStreetMapEmbedUrl(
    lat: number,
    lng: number,
    delta = 0.08,
): string {
    const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].join(',');

    return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${lat}%2C${lng}`;
}

/**
 * Google Maps search link that drops a pin on a coordinate.
 */
export function googleMapsPlaceUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps/search/?api=1&query=${lat}%2C${lng}`;
}

export type MapTravelMode = 'walking' | 'driving';

/**
 * Google Maps directions link between two coordinates.
 */
export function googleMapsDirectionsUrl(
    from: { lat: number; lng: number },
    to: { lat: number; lng: number },
    travelMode: MapTravelMode | 'transit' = 'transit',
): string {
    return `https://www.google.com/maps/dir/?api=1&origin=${from.lat}%2C${from.lng}&destination=${to.lat}%2C${to.lng}&travelmode=${travelMode}`;
}

/**
 * Embeddable Google Maps directions between two coordinates. Uses the legacy
 * keyless embed, which Google redirects to its directions embed and which
 * shows the route with its real distance and travel time.
 */
export function googleMapsEmbedDirectionsUrl(
    from: { lat: number; lng: number },
    to: { lat: number; lng: number },
    travelMode: MapTravelMode,
): string {
    const flag = travelMode === 'walking' ? 'w' : 'd';

    return `https://maps.google.com/maps?saddr=${from.lat},${from.lng}&daddr=${to.lat},${to.lng}&dirflg=${flag}&output=embed`;
}
