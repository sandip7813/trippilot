<?php

use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\GeneratedItinerary;
use App\Models\Trip;
use App\Models\User;

/**
 * @return array<string, mixed>
 */
function sampleGeneratedItinerary(): GeneratedItinerary
{
    return new GeneratedItinerary(
        title: 'Tokyo Adventure',
        days: [
            [
                'day' => 1,
                'date' => now()->addWeek()->toDateString(),
                'title' => 'Arrival in Tokyo',
                'activities' => [
                    [
                        'time' => '09:00',
                        'title' => 'Land at Narita Airport',
                        'notes' => 'Take the Narita Express to the city.',
                    ],
                    [
                        'time' => '14:00',
                        'title' => 'Explore Shibuya Crossing',
                        'notes' => null,
                    ],
                ],
            ],
        ],
        budget: [
            'estimated_total' => 3000,
            'breakdown' => [
                'lodging' => 1200,
                'food' => 600,
            ],
        ],
        packingList: ['Passport', 'Comfortable walking shoes'],
        summary: 'A balanced first day focused on arrival and iconic Tokyo sights.',
    );
}

beforeEach(function () {
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        Trip::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }

    Trip::query()->whereNotNull('_id')->delete();
});

test('users can generate an itinerary for their trip', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn(sampleGeneratedItinerary());
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'status' => 'draft',
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
        ],
    ]);

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->status->value)->toBe('planned')
        ->and($trip->itinerary['days'])->toHaveCount(1)
        ->and($trip->itinerary['summary'])->toContain('Tokyo')
        ->and($trip->itinerary['packing_list'])->toContain('Passport');
});

test('itinerary generation requires a destination', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => null,
    ]);

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertRedirect()
        ->assertSessionHasErrors(['destination']);
});

test('itinerary generation requires ai configuration', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertRedirect()
        ->assertSessionHasErrors(['ai']);
});

test('users cannot generate an itinerary for another users trip', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->post(route('trips.generate', $trip))
        ->assertForbidden();
});

test('trip show page exposes ai configuration status', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->where('aiConfigured', true));
});
