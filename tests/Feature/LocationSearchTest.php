<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot search locations', function () {
    $this->get(route('locations.search', ['q' => 'Goa']))
        ->assertRedirect(route('login'));
});

test('authenticated users can search locations when geoapify is configured', function () {
    config([
        'integrations.maps.drivers.geoapify.api_key' => 'test-key',
        'integrations.maps.drivers.geoapify.base_url' => 'https://api.geoapify.com/v1',
    ]);

    Http::fake([
        'api.geoapify.com/*' => Http::response([
            'results' => [
                [
                    'formatted' => 'Goa, India',
                    'lat' => 15.2993,
                    'lon' => 74.1240,
                    'country_code' => 'in',
                    'place_id' => 'place-goa',
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('locations.search', ['q' => 'Goa']))
        ->assertOk()
        ->assertJson([
            'enabled' => true,
            'results' => [
                [
                    'label' => 'Goa, India',
                    'lat' => 15.2993,
                    'lng' => 74.1240,
                    'place_id' => 'place-goa',
                    'country_code' => 'in',
                ],
            ],
        ]);
});

test('location search validates query length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('locations.search', ['q' => 'G']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

test('location search returns disabled payload when geoapify is not configured', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('locations.search', ['q' => 'Goa']))
        ->assertOk()
        ->assertJson([
            'enabled' => false,
            'results' => [],
        ]);
});
