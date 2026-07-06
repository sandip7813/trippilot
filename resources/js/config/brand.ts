export type LogoVariant = 'plane' | 'compass' | 'pin' | 'globe' | 'monogram';

/**
 * Active logo used across the app (sidebar, landing page, auth, favicon).
 * Change this value to switch logos — options: plane | compass | pin | globe | monogram
 */
export const ACTIVE_LOGO: LogoVariant = 'compass';

export const LOGO_OPTIONS: {
    id: LogoVariant;
    label: string;
    description: string;
    file: string;
}[] = [
    {
        id: 'plane',
        label: 'Paper plane',
        description: 'Dynamic flight path — great for speed and adventure',
        file: '/images/logos/plane.svg',
    },
    {
        id: 'compass',
        label: 'Compass',
        description: 'Classic navigation — trustworthy and travel-focused',
        file: '/images/logos/compass.svg',
    },
    {
        id: 'pin',
        label: 'Map pin',
        description: 'Location-first — ideal for destinations and itineraries',
        file: '/images/logos/pin.svg',
    },
    {
        id: 'globe',
        label: 'Globe',
        description: 'World explorer — broad, international feel',
        file: '/images/logos/globe.svg',
    },
    {
        id: 'monogram',
        label: 'TP monogram',
        description: 'Bold lettermark — clean and app-icon friendly',
        file: '/images/logos/monogram.svg',
    },
];
