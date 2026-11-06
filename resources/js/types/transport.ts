export type TripAirport = {
    iata_code: string;
    name: string;
    municipality: string | null;
    country_code: string;
    is_major: boolean;
    lat: number;
    lng: number;
    distance_km: number;
};

export type TransitMode =
    'metro' | 'train' | 'tram' | 'ferry' | 'cable_car' | 'bus';

export type TransitStop = {
    name: string;
    distance_meters: number;
    lat: number;
    lng: number;
    code: string | null;
    operator: string | null;
    network: string | null;
    routes: string[];
    wheelchair: 'yes' | 'limited' | 'no' | null;
    shelter: boolean | null;
};

export type TransitOperator = {
    name: string | null;
    network: string | null;
};

export type TransitStopGroup = {
    mode: TransitMode;
    label: string;
    operators: TransitOperator[];
    stops: TransitStop[];
};

export type TransportServiceKind = 'taxi' | 'car_rental' | 'bike_rental';

export type TransportService = {
    kind: TransportServiceKind;
    kind_label: string;
    name: string;
    operator: string | null;
    address: string | null;
    phones: string[];
    email: string | null;
    website: string | null;
    opening_hours: string | null;
    distance_meters: number;
    lat: number;
    lng: number;
};

export type TransitRide = {
    line: string;
    toward: string | null;
    stops: number | null;
    minutes: number;
};

export type AirportTransfer = {
    airport: { iata_code: string; name: string };
    drive: { distance_km: number; minutes: number } | null;
    transit: { minutes: number; rides: TransitRide[] } | null;
};

/** Response of the airport transfer endpoint for one place. */
export type AirportTransferResult = {
    transfer: AirportTransfer | null;
};

export type TripAirportsLocation = {
    label: string;
    lat: number;
    lng: number;
    airports: TripAirport[];
    transfer: AirportTransfer | null;
    transfer_loaded: boolean;
};

export type TripAirports = {
    departure: { label: string; airports: TripAirport[] } | null;
    transfers_enabled: boolean;
    locations: TripAirportsLocation[];
};

/** Public transport for one place: stops and taxis or rentals. */
export type PlacePublicTransport = {
    stops: TransitStopGroup[];
    services: TransportService[];
};

export type TripPublicTransportLocation = {
    label: string;
    lat: number;
    lng: number;
    stops: TransitStopGroup[] | null;
    services: TransportService[] | null;
};

export type TripPublicTransport = {
    locations: TripPublicTransportLocation[];
};
