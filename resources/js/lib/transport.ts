export function formatKm(km: number): string {
    return km < 10 ? `${km.toFixed(1)} km` : `${Math.round(km)} km`;
}

export function formatMeters(meters: number): string {
    return meters < 1000 ? `${meters} m` : formatKm(meters / 1000);
}

export function formatMinutes(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
