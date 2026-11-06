<?php

namespace App\Services\Transport;

use App\Data\Maps\PlaceResult;

/**
 * Turns the raw OpenStreetMap tags Geoapify returns for transport places
 * into display-ready details. OSM data is community-edited, so links and
 * contacts are validated before they reach the page: only http(s) website
 * URLs and well-formed emails.
 */
class TransportPlaceDetails
{
    /**
     * OpenStreetMap tags Indian Railways stations with the network "IR" and
     * the zone code as operator, which mean little to most travelers.
     *
     * @var array<string, string>
     */
    private const array INDIAN_RAILWAY_ZONES = [
        'CR' => 'Central Railway',
        'ECOR' => 'East Coast Railway',
        'ECR' => 'East Central Railway',
        'ER' => 'Eastern Railway',
        'KR' => 'Konkan Railway',
        'MR' => 'Metro Railway, Kolkata',
        'NCR' => 'North Central Railway',
        'NER' => 'North Eastern Railway',
        'NFR' => 'Northeast Frontier Railway',
        'NR' => 'Northern Railway',
        'NWR' => 'North Western Railway',
        'SCOR' => 'South Coast Railway',
        'SCR' => 'South Central Railway',
        'SECR' => 'South East Central Railway',
        'SER' => 'South Eastern Railway',
        'SR' => 'Southern Railway',
        'SWR' => 'South Western Railway',
        'WCR' => 'West Central Railway',
        'WR' => 'Western Railway',
    ];

    /**
     * @return array{code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}
     */
    public function stop(PlaceResult $place): array
    {
        $network = $place->tag('network');
        $operator = $place->tag('operator');

        if ($network === 'IR') {
            $network = 'Indian Railways';
            $operator = self::INDIAN_RAILWAY_ZONES[strtoupper((string) $operator)] ?? $operator;
        }

        return [
            'code' => $place->tag('ref'),
            'operator' => $operator,
            'network' => $network,
            'routes' => $this->routes($place->tag('route_ref')),
            'wheelchair' => $this->wheelchair($place->tag('wheelchair')),
            'shelter' => match ($place->tag('shelter')) {
                'yes' => true,
                'no' => false,
                default => null,
            },
        ];
    }

    /**
     * Contact details for a taxi stand or rental shop. Returns null for
     * places without a real name or without any way to reach or visit them,
     * which in OSM are mostly unlabelled map points.
     *
     * @return array{name: string, operator: string|null, address: string|null, phones: list<string>, email: string|null, website: string|null, opening_hours: string|null}|null
     */
    public function service(PlaceResult $place): ?array
    {
        $name = $place->tag('name');

        if ($name === null) {
            return null;
        }

        $details = [
            'name' => $name,
            'operator' => $place->tag('operator') ?? $place->tag('brand'),
            'address' => $place->address,
            'phones' => $this->phones($place->tag('phone') ?? $place->tag('contact:phone')),
            'email' => $this->email($place->tag('email') ?? $place->tag('contact:email')),
            'website' => $this->websiteUrl($place->tag('website') ?? $place->tag('contact:website')),
            'opening_hours' => $place->tag('opening_hours'),
        ];

        $reachable = $details['phones'] !== []
            || $details['email'] !== null
            || $details['website'] !== null
            || $details['opening_hours'] !== null;

        return $reachable ? $details : null;
    }

    /**
     * @return list<string>
     */
    private function routes(?string $routeRef): array
    {
        if ($routeRef === null) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(trim(...), explode(';', $routeRef)),
            fn (string $route): bool => $route !== '',
        )));
    }

    private function wheelchair(?string $value): ?string
    {
        return match ($value) {
            'yes', 'designated' => 'yes',
            'limited' => 'limited',
            'no' => 'no',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function phones(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $phones = array_filter(
            array_map(trim(...), preg_split('/[;,]/', $value) ?: []),
            fn (string $phone): bool => preg_match('/^\+?[\d\s\-().]{6,}$/', $phone) === 1,
        );

        return array_slice(array_values(array_unique($phones)), 0, 2);
    }

    private function email(?string $value): ?string
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    private function websiteUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $url = preg_match('/^[a-z][a-z0-9+.-]*:/i', $value) ? $value : 'https://'.$value;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
