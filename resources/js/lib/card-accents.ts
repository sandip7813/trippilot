export const tripCardAccents = [
    'from-teal-500 via-cyan-500 to-sky-500',
    'from-violet-500 via-purple-500 to-fuchsia-500',
    'from-orange-500 via-rose-500 to-pink-500',
    'from-emerald-500 via-teal-500 to-cyan-500',
    'from-amber-500 via-orange-500 to-red-500',
    'from-indigo-500 via-blue-500 to-cyan-500',
] as const;

export function tripCardAccent(index: number): string {
    return tripCardAccents[index % tripCardAccents.length];
}

export const featureIconAccents = [
    'from-teal-500 to-cyan-500',
    'from-violet-500 to-purple-500',
    'from-orange-500 to-rose-500',
    'from-emerald-500 to-teal-500',
    'from-sky-500 to-indigo-500',
    'from-amber-500 to-orange-500',
] as const;

export function featureIconAccent(index: number): string {
    return featureIconAccents[index % featureIconAccents.length];
}
