<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config([
        'integrations.maps.drivers.geoapify.api_key' => 'geo-key',
        'integrations.hotels.page_size' => 2,
    ]);
});

/**
 * @param  list<string>  $names
 * @return array<string, mixed>
 */
function geoapifyHotelsResponse(array $names = ['OSM Grand Hotel', 'Bare Bones Lodge']): array
{
    $features = array_map(fn (string $name, int $index): array => [
        'properties' => [
            'name' => $name,
            'address_line2' => "{$index} Beach Road, Panaji - 403001, Goa, India",
            'lat' => 15.4993 + $index / 1000,
            'lon' => 73.8287,
            'distance' => 100 * ($index + 1),
            'place_id' => "geo-place-{$index}",
        ],
    ], $names, array_keys($names));

    $features[0]['properties'] += [
        'accommodation' => ['stars' => 3, 'rooms' => 104],
        'contact' => ['phone' => '+91-8799901500', 'email' => 'info@osm-grand.example.com'],
        'website' => 'http://www.osm-grand.example.com/',
        'opening_hours' => '24/7',
        'facilities' => ['internet_access' => true, 'smoking' => false, 'swimming_pool' => true],
    ];

    return ['features' => $features];
}

function geoapifyRequestCount(): int
{
    return Http::recorded(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places'))->count();
}

/**
 * @return array<string, mixed>
 */
function tripDestination(string $label = 'Goa, India', ?float $lat = 15.4989, ?float $lng = 73.8278): array
{
    return [
        'label' => $label,
        'lat' => $lat,
        'lng' => $lng,
        'place_id' => 'test-destination',
        'country_code' => 'in',
    ];
}

/**
 * @return array{sequence: int, location: array<string, mixed>, nights: int}
 */
function tripWaypoint(int $sequence, string $label, float $lat, float $lng): array
{
    return ['sequence' => $sequence, 'location' => tripDestination($label, $lat, $lng), 'nights' => 2];
}

test('trip show lists hotels near the destination', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse(['OSM Grand Hotel', 'Bare Bones Lodge', 'Third Hotel']))]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->loadDeferredProps(fn ($page) => $page
                ->has('hotels.locations', 1)
                ->where('hotels.locations.0.label', 'Goa, India')
                ->where('hotels.locations.0.available', true)
                ->has('hotels.locations.0.hotels', 2)
                ->where('hotels.locations.0.has_more', true)
                ->where('hotels.locations.0.next_offset', 2)
                ->where('hotels.locations.0.hotels.0.name', 'OSM Grand Hotel')
                ->where('hotels.locations.0.hotels.0.address', '0 Beach Road, Panaji - 403001, Goa, India')
                ->where('hotels.locations.0.hotels.0.distance_meters', 100)
                ->where('hotels.locations.0.hotels.0.stars', 3)
                ->where('hotels.locations.0.hotels.0.rooms', 104)
                ->where('hotels.locations.0.hotels.0.facilities', ['Internet access', 'Swimming pool'])
                ->where('hotels.locations.0.hotels.0.phone', '+91-8799901500')
                ->where('hotels.locations.0.hotels.0.email', 'info@osm-grand.example.com')
                ->where('hotels.locations.0.hotels.0.website', 'http://www.osm-grand.example.com/')
                ->where('hotels.locations.0.hotels.0.opening_hours', '24/7')
                ->where('hotels.locations.0.hotels.1.name', 'Bare Bones Lodge')
                ->where('hotels.locations.0.hotels.1.stars', null)
                ->where('hotels.locations.0.hotels.1.phone', null)
                ->where('hotels.locations.0.hotels.1.facilities', [])));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places')
        && $request['categories'] === 'accommodation.hotel'
        && $request['conditions'] === 'named'
        && $request['filter'] === 'circle:73.8278,15.4989,10000'
        && (int) $request['limit'] === 3
        && (int) $request['offset'] === 0);
});

test('there is no next page when the provider has no more hotels', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse(['Only Hotel']))]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('hotels.locations.0.hotels', 1)
                ->where('hotels.locations.0.has_more', false)));
});

test('road trip show lists hotels near the final destination only', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse())]);

    $user = User::factory()->create();
    $trip = Trip::factory()->road()->forUser($user)->create([
        'destination' => tripDestination(),
        'waypoints' => [tripWaypoint(1, 'Pune, India', 18.52, 73.85)],
    ]);

    $this->actingAs($user)
        ->get(route('road-trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('RoadTrips/Show')
            ->loadDeferredProps(fn ($page) => $page
                ->has('hotels.locations', 1)
                ->where('hotels.locations.0.label', 'Goa, India')));
});

test('multi-city trips get a location per stay with only the first loaded', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse())]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'route_mode' => 'multi_city',
        'origin' => tripDestination('Kolkata, India', 22.5726, 88.3639),
        'waypoints' => [
            tripWaypoint(1, 'Delhi, India', 28.6139, 77.2090),
            tripWaypoint(2, 'Agra, India', 27.1767, 78.0081),
            tripWaypoint(3, 'Jaipur, India', 26.9124, 75.7873),
        ],
        'destination' => tripDestination('Jaipur, India', 26.9124, 75.7873),
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('hotels.locations', 3)
                ->where('hotels.locations.0.label', 'Delhi, India')
                ->where('hotels.locations.1.label', 'Agra, India')
                ->where('hotels.locations.2.label', 'Jaipur, India')
                ->has('hotels.locations.0.hotels', 2)
                ->where('hotels.locations.1.hotels', null)
                ->where('hotels.locations.2.hotels', null)));

    expect(geoapifyRequestCount())->toBe(1);
});

test('the origin is not listed as a stay when the trip returns home', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse())]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'route_mode' => 'multi_city',
        'origin' => tripDestination('Kolkata, India', 22.5726, 88.3639),
        'waypoints' => [
            tripWaypoint(1, 'Delhi, India', 28.6139, 77.2090),
            tripWaypoint(2, 'Agra, India', 27.1767, 78.0081),
        ],
        'destination' => tripDestination('Kolkata, India', 22.5726, 88.3639),
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->has('hotels.locations', 2)
                ->where('hotels.locations.0.label', 'Delhi, India')
                ->where('hotels.locations.1.label', 'Agra, India')));
});

test('the hotels endpoint returns the next page for a location', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse(['Third Hotel', 'Fourth Hotel']))]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'route_mode' => 'multi_city',
        'origin' => tripDestination('Kolkata, India', 22.5726, 88.3639),
        'waypoints' => [
            tripWaypoint(1, 'Delhi, India', 28.6139, 77.2090),
            tripWaypoint(2, 'Agra, India', 27.1767, 78.0081),
        ],
        'destination' => tripDestination('Agra, India', 27.1767, 78.0081),
    ]);

    $this->actingAs($user)
        ->getJson(route('trips.hotels', ['trip' => $trip, 'location' => 1, 'offset' => 2]))
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('hotels.0.name', 'Third Hotel')
        ->assertJsonPath('hotels.1.name', 'Fourth Hotel')
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_offset', 4);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.geoapify.com/v2/places')
        && $request['filter'] === 'circle:78.0081,27.1767,10000'
        && (int) $request['offset'] === 2);
});

test('hotel pages are cached per location and offset', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse())]);

    $user = User::factory()->create();
    $first = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);
    $second = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);

    foreach ([$first, $second] as $trip) {
        $this->actingAs($user)
            ->getJson(route('trips.hotels', ['trip' => $trip, 'location' => 0, 'offset' => 0]))
            ->assertOk();
    }

    $this->actingAs($user)
        ->getJson(route('trips.hotels', ['trip' => $first, 'location' => 0, 'offset' => 2]))
        ->assertOk();

    expect(geoapifyRequestCount())->toBe(2);
});

test('the hotels endpoint rejects other users, bad input and unknown locations', function () {
    Http::fake(['api.geoapify.com/*' => Http::response(geoapifyHotelsResponse())]);

    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create(['destination' => tripDestination()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('trips.hotels', ['trip' => $trip, 'location' => 0, 'offset' => 0]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route('trips.hotels', ['trip' => $trip, 'location' => 0]))
        ->assertUnprocessable();

    $this->actingAs($owner)
        ->getJson(route('trips.hotels', ['trip' => $trip, 'location' => 5, 'offset' => 0]))
        ->assertNotFound();
});

test('a provider failure is reported and not cached', function () {
    Http::fake(['api.geoapify.com/*' => Http::response([], 500)]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page
                ->where('hotels.locations.0.available', false)
                ->where('hotels.locations.0.hotels', [])
                ->where('hotels.locations.0.has_more', false)));

    expect(Cache::has('hotels:geoapify:v4:15.50,73.83:0'))->toBeFalse();
});

test('destinations without coordinates show no hotels section', function () {
    Http::fake();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => tripDestination('Goa, India', null, null)]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page->where('hotels', null)));

    expect(geoapifyRequestCount())->toBe(0);
});

test('hotels are hidden without a geoapify key', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => null]);

    Http::fake();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['destination' => tripDestination()]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($page) => $page->where('hotels', null)));

    expect(geoapifyRequestCount())->toBe(0);
});
