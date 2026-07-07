/**
 * OpenStreetMap embed URL for an inline map preview.
 */
export function openStreetMapEmbedUrl(lat: number, lng: number, delta = 0.08): string {
    const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].join(',');

    return `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${lat}%2C${lng}`;
}
