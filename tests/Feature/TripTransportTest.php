<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    $airportsPath = tempnam(sys_get_temp_dir(), 'airports');

    file_put_contents($airportsPath, implode("\n", [
        'iata_code,name,municipality,country_code,type,latitude,longitude',
        'AGR,"Agra Airport",Agra,IN,medium_airport,27.15583,77.96089',
        'CCU,"Kolkata Airport",Kolkata,IN,large_airport,22.65401,88.44765',
        'DEL,"Indira Gandhi International Airport","New Delhi",IN,large_airport,28.55563,77.09519',
        'IXL,"Leh Airport",Leh,IN,medium_airport,34.1359,77.5465',
        'KDU,"Skardu Airport",Skardu,PK,medium_airport,35.335,75.536',
    ]));

    config([
        'integrations.maps.drivers.geoapify.api_key' => 'geo-key',
        'integrations.airports.data_path' => $airportsPath,
        'integrations.airports.max_results' => 3,
        'integrations.airports.max_distance_km' => 300,
        'integrations.transport.stops_per_mode' => 2,
        'integrations.transport.transfer_transit_max_km' => 80,
    ]);
});

/**
 * @return array<string, mixed>
 */
function transportPlace(string $label, ?float $lat, ?float $lng, string $countryCode = 'in'): array
{
    return [
        'label' => $label,
        'lat' => $lat,
        'lng' => $lng,
        'place_id' => null,
        'country_code' => $countryCode,
    ];
}

/**
 * A Geoapify place feature. Raw OpenStreetMap tags go under datasource.raw,
 * as Geoapify returns them; a null name leaves the place unnamed in OSM.
 *
 * @param  list<string>  $categories
 * @param  array<string, string>  $tags
 * @return array<string, mixed>
 */
function transportPlaceFeature(?string $name, array $categories, float $lat, float $lng, array $tags = []): array
{
    return [
        'type' => 'Feature',
        'properties' => [
            'name' => $name,
            'address_line1' => 'Connaught Place',
            'formatted' => 'Connaught Place, New Delhi, India',
            'categories' => $categories,
            'place_id' => md5($name.implode(',', $categories).$lat),
            'datasource' => ['raw' => array_filter(['name' => $name, ...$tags])],
        ],
        'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
    ];
}

/**
 * @param  array<string, string>  $tags
 * @return array<string, mixed>
 */
function transportStopFeature(string $name, string $category, float $lat, float $lng, array $tags = []): array
{
    return transportPlaceFeature($name, ['public_transport', $category], $lat, $lng, $tags);
}

/**
 * @param  list<array{distance: int, time: float, instruction: array{text: string}}>  $steps
 * @return array<string, mixed>
 */
function transportRouteResponse(int $distanceMeters, int $seconds, array $steps = []): array
{
    return [
        'features' => [[
            'type' => 'Feature',
            'properties' => [
                'distance' => $distanceMeters,
                'time' => $seconds,
                'legs' => [['distance' => $distanceMeters, 'time' => $seconds, 'steps' => $steps]],
            ],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[77.09, 28.55], [77.21, 28.63]]],
        ]],
    ];
}

/**
 * Fakes Geoapify: rail-type and bus stops with OSM operator tags, taxi and
 * rental services, a 20-minute drive and a 57-minute public transport route
 * with two rides. Pass a status to make public transport routing fail.
 */
function fakeGeoapifyTransport(int $transitStatus = 200): void
{
    Http::preventStrayRequests();

    Http::fake([
        'api.geoapify.com/v2/places*' => function (Request $request) {
            $delhiMetro = ['network' => 'Delhi Metro', 'operator' => 'Delhi Metro Rail Corporation Limited'];

            if ($request['categories'] === 'public_transport.bus') {
                return Http::response(['features' => [
                    transportStopFeature('Hanuman Mandir', 'public_transport.bus', 28.6304, 77.2140, ['shelter' => 'yes']),
                    transportStopFeature('Shivaji Stadium', 'public_transport.bus', 28.6330, 77.2155),
                    transportStopFeature('Palika Kendra', 'public_transport.bus', 28.6287, 77.2165, [
                        'operator' => 'Delhi Transport Corporation',
                        'route_ref' => '016;091; 100;016',
                        'wheelchair' => 'no',
                    ]),
                ]]);
            }

            if ($request['categories'] === 'service.taxi,rental.car,rental.bicycle') {
                return Http::response(['features' => [
                    transportPlaceFeature('Western Court Taxi Service', ['service', 'service.taxi'], 28.6380, 77.2200, [
                        'phone' => '+91-11-2336-8036;+91-11-2336-6036',
                        'website' => 'www.western-tourism.com',
                    ]),
                    transportPlaceFeature('Planet Green Bikes', ['rental', 'rental.bicycle', 'rental.car'], 28.6390, 77.2210, [
                        'website' => 'javascript:alert(1)',
                        'email' => 'not-an-email',
                        'opening_hours' => 'Mo-Fr 10:30-17:30',
                    ]),
                    transportPlaceFeature(null, ['service', 'service.taxi'], 28.6320, 77.2170, ['phone' => '+91 98100 00000']),
                    transportPlaceFeature('Car Rentals', ['rental', 'rental.car'], 28.6325, 77.2175),
                ]]);
            }

            return Http::response(['features' => [
                transportStopFeature('Barakhambha Road', 'public_transport.subway', 28.6298, 77.2250, [...$delhiMetro, 'ref' => 'BKRM']),
                transportStopFeature('Rajiv Chowk', 'public_transport.subway', 28.6327, 77.2193, [
                    ...$delhiMetro,
                    'ref' => 'RCK',
                    'wikipedia' => 'en:Rajiv Chowk metro station',
                ]),
                transportStopFeature('Rajiv Chowk', 'public_transport.light_rail', 28.6328, 77.2194, ['wheelchair' => 'designated']),
                transportStopFeature('New Delhi', 'public_transport.train', 28.6419, 77.2217, ['network' => 'IR', 'operator' => 'NR', 'ref' => 'NDLS']),
                transportStopFeature('Shivaji Stadium', 'public_transport.subway', 28.6289, 77.2113, [...$delhiMetro, 'ref' => 'SJSU']),
            ]]);
        },
        'api.geoapify.com/v1/routing*' => function (Request $request) use ($transitStatus) {
            if ($request['mode'] === 'drive') {
                return Http::response(transportRouteResponse(18500, 1200));
            }

            if ($transitStatus !== 200) {
                return Http::response(['statusCode' => $transitStatus, 'message' => 'Cannot reach destination - too far from a transit stop'], $transitStatus);
            }

            return Http::response(transportRouteResponse(16900, 3441, [
                ['distance' => 28, 'time' => 25, 'instruction' => ['text' => 'Walk east.']],
                ['distance' => 3011, 'time' => 674, 'instruction' => ['text' => 'Take the 543B toward 543B: Anand Vihar ISBT → IGI Airport. (3 stops)']],
                ['distance' => 21, 'time' => 18, 'instruction' => ['text' => 'Enter the Delhi Aerocity Station.']],
                ['distance' => 12610, 'time' => 1660, 'instruction' => ['text' => 'Transfer to take the AEx toward Airport Express Line (Dwarka Sector 21 --> New Delhi Airport Express Terminal). (1 stop)']],
                ['distance' => 0, 'time' => 0, 'instruction' => ['text' => 'You have arrived at your destination.']],
            ]));
        },
    ]);
}

function geoapifyTransportRequestCount(string $endpoint): int
{
    return Http::recorded(fn ($request) => str_contains($request->url(), "api.geoapify.com/{$endpoint}"))->count();
}

/**
 * Kolkata → New Delhi → Agra, returning home.
 *
 * @return array<string, mixed>
 */
function multiCityTransportTrip(): array
{
    return [
        'route_mode' => 'multi_city',
        'origin' => transportPlace('Kolkata, India', 22.5726, 88.3639),
        'waypoints' => [
            ['sequence' => 1, 'location' => transportPlace('New Delhi, India', 28.6315, 77.2167), 'nights' => 2],
            ['sequence' => 2, 'location' => transportPlace('Agra, India', 27.1767, 78.0081), 'nights' => 1],
        ],
        'destination' => transportPlace('Agra, India', 27.1767, 78.0081),
    ];
}

test('the airports tab lists airports for the departure city and every stay', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->loadDeferredProps('airports', fn ($page) => $page
                ->where('airports.transfers_enabled', true)
                ->where('airports.departure.label', 'Kolkata, India')
                ->where('airports.departure.airports.0.iata_code', 'CCU')
                ->where('airports.departure.airports.0.is_major', true)
                ->where('airports.departure.airports.0.distance_km', 12.5)
                ->has('airports.locations', 2)
                ->where('airports.locations.0.label', 'New Delhi, India')
                ->where('airports.locations.0.airports.0.iata_code', 'DEL')
                ->where('airports.locations.0.airports.1.iata_code', 'AGR')
                ->where('airports.locations.1.label', 'Agra, India')
                ->where('airports.locations.1.airports.0.iata_code', 'AGR')));
});

test('the airports tab loads the airport transfer for the first stay only, without searching for stops', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->where('airports.locations.0.transfer_loaded', true)
                ->where('airports.locations.0.transfer.airport.iata_code', 'DEL')
                ->where('airports.locations.0.transfer.drive', ['distance_km' => 18.5, 'minutes' => 20])
                ->where('airports.locations.0.transfer.transit.minutes', 57)
                ->where('airports.locations.0.transfer.transit.rides', [
                    ['line' => '543B', 'toward' => 'Anand Vihar ISBT → IGI Airport', 'stops' => 3, 'minutes' => 11],
                    ['line' => 'AEx', 'toward' => 'Airport Express Line (Dwarka Sector 21 --> New Delhi Airport Express Terminal)', 'stops' => 1, 'minutes' => 28],
                ])
                ->where('airports.locations.1.transfer_loaded', false)
                ->where('airports.locations.1.transfer', null)));

    expect(geoapifyTransportRequestCount('v1/routing'))->toBe(2)
        ->and(geoapifyTransportRequestCount('v2/places'))->toBe(0);
});

test('the public transport tab loads the first stay only, without routing', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('public-transport', fn ($page) => $page
                ->has('publicTransport.locations', 2)
                ->where('publicTransport.locations.0.label', 'New Delhi, India')
                ->has('publicTransport.locations.0.stops', 3)
                ->where('publicTransport.locations.0.stops.0.mode', 'metro')
                ->has('publicTransport.locations.0.stops.0.stops', 2)
                ->where('publicTransport.locations.0.stops.0.stops.0.name', 'Rajiv Chowk')
                ->where('publicTransport.locations.0.stops.0.stops.1.name', 'Shivaji Stadium')
                ->where('publicTransport.locations.0.stops.1.mode', 'train')
                ->where('publicTransport.locations.0.stops.2.mode', 'bus')
                ->has('publicTransport.locations.0.services', 2)
                ->where('publicTransport.locations.1.label', 'Agra, India')
                ->where('publicTransport.locations.1.stops', null)
                ->where('publicTransport.locations.1.services', null)));

    expect(geoapifyTransportRequestCount('v2/places'))->toBe(3)
        ->and(geoapifyTransportRequestCount('v1/routing'))->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places')
        && $request['categories'] === 'public_transport.bus'
        && $request['filter'] === 'circle:77.2167,28.6315,3000');
});

test('stops show their operator, station code, routes and accessibility, but no wikipedia link', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('New Delhi, India', 28.6315, 77.2167)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('public-transport', fn ($page) => $page
                ->where('publicTransport.locations.0.stops.0.mode', 'metro')
                ->where('publicTransport.locations.0.stops.0.operators', [
                    ['name' => 'Delhi Metro Rail Corporation Limited', 'network' => 'Delhi Metro'],
                ])
                ->where('publicTransport.locations.0.stops.0.stops.0.name', 'Rajiv Chowk')
                ->where('publicTransport.locations.0.stops.0.stops.0.code', 'RCK')
                ->where('publicTransport.locations.0.stops.0.stops.0.wheelchair', 'yes')
                ->missing('publicTransport.locations.0.stops.0.stops.0.wikipedia_url')
                ->where('publicTransport.locations.0.stops.1.operators', [
                    ['name' => 'Northern Railway', 'network' => 'Indian Railways'],
                ])
                ->where('publicTransport.locations.0.stops.1.stops.0.code', 'NDLS')
                ->where('publicTransport.locations.0.stops.2.mode', 'bus')
                ->has('publicTransport.locations.0.stops.2.stops', 3)
                ->where('publicTransport.locations.0.stops.2.stops.0.name', 'Shivaji Stadium')
                ->where('publicTransport.locations.0.stops.2.stops.1.name', 'Hanuman Mandir')
                ->where('publicTransport.locations.0.stops.2.stops.1.shelter', true)
                ->where('publicTransport.locations.0.stops.2.stops.2.name', 'Palika Kendra')
                ->where('publicTransport.locations.0.stops.2.stops.2.routes', ['016', '091', '100'])
                ->where('publicTransport.locations.0.stops.2.stops.2.wheelchair', 'no')
                ->where('publicTransport.locations.0.stops.2.operators', [
                    ['name' => 'Delhi Transport Corporation', 'network' => null],
                ])));
});

test('taxis and rentals list only named places that can be contacted, with safe links', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('New Delhi, India', 28.6315, 77.2167)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('public-transport', fn ($page) => $page
                ->has('publicTransport.locations.0.services', 2)
                ->where('publicTransport.locations.0.services.0.name', 'Western Court Taxi Service')
                ->where('publicTransport.locations.0.services.0.kind', 'taxi')
                ->where('publicTransport.locations.0.services.0.kind_label', 'Taxi')
                ->where('publicTransport.locations.0.services.0.phones', ['+91-11-2336-8036', '+91-11-2336-6036'])
                ->where('publicTransport.locations.0.services.0.website', 'https://www.western-tourism.com')
                ->where('publicTransport.locations.0.services.0.address', 'Connaught Place, New Delhi, India')
                ->where('publicTransport.locations.0.services.1.name', 'Planet Green Bikes')
                ->where('publicTransport.locations.0.services.1.kind_label', 'Car & bike rental')
                ->where('publicTransport.locations.0.services.1.website', null)
                ->where('publicTransport.locations.0.services.1.email', null)
                ->where('publicTransport.locations.0.services.1.opening_hours', 'Mo-Fr 10:30-17:30')));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places')
        && $request['categories'] === 'service.taxi,rental.car,rental.bicycle'
        && $request['filter'] === 'circle:77.2167,28.6315,10000');
});

test('nearby airports in the same country are preferred over a closer one across the border', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('Leh, India', 34.1526, 77.5771)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->has('airports.locations.0.airports', 1)
                ->where('airports.locations.0.airports.0.iata_code', 'IXL')));
});

test('a remote place still shows its single nearest airport when none is in range', function () {
    fakeGeoapifyTransport();
    config(['integrations.airports.max_distance_km' => 5]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('Shimla, India', 31.1048, 77.1734)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->has('airports.locations.0.airports', 1)
                ->where('airports.locations.0.airports.0.iata_code', 'DEL')));
});

test('public transport is not routed from an airport beyond the transit distance', function () {
    fakeGeoapifyTransport();
    config(['integrations.transport.transfer_transit_max_km' => 10]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('New Delhi, India', 28.6315, 77.2167)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->where('airports.locations.0.transfer.drive.minutes', 20)
                ->where('airports.locations.0.transfer.transit', null)));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v1/routing')
        && $request['mode'] === 'approximated_transit');
});

test('a failed public transport route keeps the drive estimate and is not retried on every visit', function () {
    fakeGeoapifyTransport(transitStatus: 400);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('New Delhi, India', 28.6315, 77.2167)]);

    $transitRequestCount = fn (): int => Http::recorded(
        fn ($request) => str_contains($request->url(), 'api.geoapify.com/v1/routing') && $request['mode'] === 'approximated_transit',
    )->count();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->where('airports.locations.0.transfer.drive.minutes', 20)
                ->where('airports.locations.0.transfer.transit', null)));

    $requestsAfterFirstVisit = $transitRequestCount();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('airports', fn ($page) => $page
                ->where('airports.locations.0.transfer.transit', null)));

    expect($requestsAfterFirstVisit)->toBeGreaterThan(0)
        ->and($transitRequestCount())->toBe($requestsAfterFirstVisit);
});

test('the airport transfer endpoint loads another stay on demand and caches it', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $first = Trip::factory()->forUser($user)->create(multiCityTransportTrip());
    $second = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    foreach ([$first, $second] as $trip) {
        $this->actingAs($user)
            ->getJson(route('trips.airports.transfer', ['trip' => $trip, 'location' => 1]))
            ->assertOk()
            ->assertJsonPath('transfer.airport.iata_code', 'AGR')
            ->assertJsonPath('transfer.drive.minutes', 20);
    }

    expect(geoapifyTransportRequestCount('v1/routing'))->toBe(2)
        ->and(geoapifyTransportRequestCount('v2/places'))->toBe(0);
});

test('the airport transfer endpoint routes from another airport listed for the place', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    $this->actingAs($user)
        ->getJson(route('trips.airports.transfer', ['trip' => $trip, 'location' => 0, 'airport' => 'agr']))
        ->assertOk()
        ->assertJsonPath('transfer.airport.iata_code', 'AGR')
        ->assertJsonPath('transfer.drive.minutes', 20)
        ->assertJsonPath('transfer.transit', null);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v1/routing')
        && $request['mode'] === 'drive'
        && $request['waypoints'] === '27.15583,77.96089|28.6315,77.2167');

    expect(geoapifyTransportRequestCount('v1/routing'))->toBe(1);
});

test('the airport transfer endpoint refuses airports not listed for the place', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    $this->actingAs($user)
        ->getJson(route('trips.airports.transfer', ['trip' => $trip, 'location' => 0, 'airport' => 'CCU']))
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('trips.airports.transfer', ['trip' => $trip, 'location' => 0, 'airport' => 'DE1']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['airport' => 'The airport field format is invalid.']);

    expect(geoapifyTransportRequestCount('v1/routing'))->toBe(0);
});

test('the public transport endpoint loads another stay on demand and caches it', function () {
    fakeGeoapifyTransport();

    $user = User::factory()->create();
    $first = Trip::factory()->forUser($user)->create(multiCityTransportTrip());
    $second = Trip::factory()->forUser($user)->create(multiCityTransportTrip());

    foreach ([$first, $second] as $trip) {
        $this->actingAs($user)
            ->getJson(route('trips.public-transport', ['trip' => $trip, 'location' => 1]))
            ->assertOk()
            ->assertJsonPath('stops.0.mode', 'metro')
            ->assertJsonPath('services.0.name', 'Western Court Taxi Service');
    }

    expect(geoapifyTransportRequestCount('v2/places'))->toBe(3)
        ->and(geoapifyTransportRequestCount('v1/routing'))->toBe(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places')
        && $request['filter'] === 'circle:78.0081,27.1767,3000');
});

test('the on-demand endpoints reject other users, bad input and unknown stays', function (string $routeName) {
    fakeGeoapifyTransport();

    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create(multiCityTransportTrip());

    $this->actingAs(User::factory()->create())
        ->getJson(route($routeName, ['trip' => $trip, 'location' => 0]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route($routeName, ['trip' => $trip]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location' => 'The location field is required.']);

    $this->actingAs($owner)
        ->getJson(route($routeName, ['trip' => $trip, 'location' => 5]))
        ->assertNotFound();

    expect(geoapifyTransportRequestCount(''))->toBe(0);
})->with(['trips.airports.transfer', 'trips.public-transport']);

test('without a geoapify key only airports are shown and the public transport tab is hidden', function () {
    Http::preventStrayRequests();
    Http::fake();
    config(['integrations.maps.drivers.geoapify.api_key' => null]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('New Delhi, India', 28.6315, 77.2167)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(['airports', 'public-transport'], fn ($page) => $page
                ->where('airports.transfers_enabled', false)
                ->where('airports.locations.0.airports.0.iata_code', 'DEL')
                ->where('airports.locations.0.transfer', null)
                ->where('airports.locations.0.transfer_loaded', false)
                ->where('publicTransport', null)));

    foreach (['trips.airports.transfer', 'trips.public-transport'] as $routeName) {
        $this->actingAs($user)
            ->getJson(route($routeName, ['trip' => $trip, 'location' => 0]))
            ->assertNotFound();
    }

    Http::assertNothingSent();
});

test('trips without mapped places show neither tab', function () {
    Http::preventStrayRequests();
    Http::fake();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => transportPlace('Goa, India', null, null)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(['airports', 'public-transport'], fn ($page) => $page
                ->where('airports', null)
                ->where('publicTransport', null)));

    Http::assertNothingSent();
});

test('the refresh command keeps only airports with scheduled flights', function () {
    $path = sys_get_temp_dir().'/trippilot-airports-'.uniqid().'.csv';
    config(['integrations.airports.data_path' => $path]);

    Http::preventStrayRequests();
    Http::fake([
        'davidmegginson.github.io/*' => Http::response(implode("\n", [
            '"id","ident","type","name","latitude_deg","longitude_deg","elevation_ft","continent","iso_country","iso_region","municipality","scheduled_service","icao_code","iata_code","gps_code","local_code","home_link","wikipedia_link","keywords"',
            '1,"VIDP","large_airport","Indira Gandhi International Airport",28.55563,77.09519,777,"AS","IN","IN-DL","New Delhi","yes","VIDP","DEL","VIDP",,,,',
            '2,"VI01","heliport","Rohini Heliport",28.74,77.11,700,"AS","IN","IN-DL","Delhi","yes",,"RHN",,,,,',
            '3,"VI02","small_airport","Private Strip",28.1,77.2,700,"AS","IN","IN-HR","Palwal","no",,"PVT",,,,,',
            '4,"VI03","medium_airport","No Code Field",28.2,77.3,700,"AS","IN","IN-HR","Palwal","yes",,,,,,,',
            '5,"VIAG","medium_airport","Agra Airport",27.15583,77.96089,551,"AS","IN","IN-UP","Agra","yes","VIAG","AGR","VIAG",,,,',
        ])),
    ]);

    $this->artisan('trippilot:refresh-airports')
        ->expectsOutputToContain('Saved 2 airports')
        ->assertSuccessful();

    expect(file($path, FILE_IGNORE_NEW_LINES))->toBe([
        'iata_code,name,municipality,country_code,type,latitude,longitude',
        'AGR,"Agra Airport",Agra,IN,medium_airport,27.15583,77.96089',
        'DEL,"Indira Gandhi International Airport","New Delhi",IN,large_airport,28.55563,77.09519',
    ]);

    unlink($path);
});

test('the refresh command fails without overwriting data when the download fails', function () {
    $path = sys_get_temp_dir().'/trippilot-airports-'.uniqid().'.csv';
    file_put_contents($path, 'existing');
    config(['integrations.airports.data_path' => $path]);

    Http::preventStrayRequests();
    Http::fake(['davidmegginson.github.io/*' => Http::response('', 503)]);

    $this->artisan('trippilot:refresh-airports')->assertFailed();

    expect(file_get_contents($path))->toBe('existing');

    unlink($path);
});
