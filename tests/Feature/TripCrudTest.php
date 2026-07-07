<?php

use App\Models\Trip;
use App\Models\User;

/**
 * @return array<string, mixed>
 */
function validTripPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Tokyo Adventure',
        'destination' => [
            'label' => 'Tokyo, Japan',
        ],
        'type' => 'vacation',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
        'budget' => 3000,
        'travelers' => 2,
        'notes' => 'Cherry blossom season',
    ], $overrides);
}

function skipUnlessMongoDbAvailable(): void
{
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        Trip::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }
}

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('guests cannot access trip pages', function () {
    $this->get(route('trips.index'))->assertRedirect(route('login'));
    $this->get(route('trips.create'))->assertRedirect(route('login'));
});

test('authenticated users can list their trips', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Trip::factory()->forUser($user)->create(['title' => 'My Trip']);
    Trip::factory()->forUser($other)->create(['title' => 'Other Trip']);

    $this->actingAs($user)
        ->get(route('trips.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Index')
            ->has('trips', 1)
            ->where('trips.0.title', 'My Trip'));
});

test('users can create a trip with structured locations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayload([
            'travel_style' => 'family',
            'origin' => [
                'label' => 'Mumbai, India',
                'lat' => 19.076,
                'lng' => 72.8777,
            ],
        ]))
        ->assertRedirect();

    $trip = Trip::query()
        ->where('user_id', $user->id)
        ->where('title', 'Tokyo Adventure')
        ->first();

    expect($trip)->not->toBeNull()
        ->and($trip->title)->toBe('Tokyo Adventure')
        ->and($trip->destination)->toMatchArray([
            'label' => 'Tokyo, Japan',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
        ])
        ->and($trip->origin)->toMatchArray([
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
            'place_id' => null,
        ])
        ->and($trip->travel_style?->value)->toBe('family')
        ->and($trip->status->value)->toBe('draft');
});

test('road trips require an origin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayload([
            'type' => 'road',
            'origin' => ['label' => ''],
        ]))
        ->assertSessionHasErrors(['origin.label']);
});

test('trip create page includes default origin from profile home city', function () {
    $user = User::factory()->create([
        'travel_preferences' => [
            'home_city' => [
                'label' => 'Delhi, India',
                'lat' => null,
                'lng' => null,
                'place_id' => null,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('trips.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Create')
            ->where('defaultOrigin.label', 'Delhi, India')
            ->has('travelStyles'));
});

test('trip creation validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), [])
        ->assertSessionHasErrors(['title', 'type', 'travelers']);
});

test('users can view their own trip', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->where('trip.id', (string) $trip->id));
});

test('users cannot view another users trip', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->get(route('trips.show', $trip))
        ->assertForbidden();
});

test('users can update their trip', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['title' => 'Old Title']);

    $this->actingAs($user)
        ->put(route('trips.update', $trip), validTripPayload(['title' => 'New Title']))
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh()->title)->toBe('New Title');
});

test('users cannot update another users trip', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->put(route('trips.update', $trip), validTripPayload())
        ->assertForbidden();
});

test('users can delete their trip', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->delete(route('trips.destroy', $trip))
        ->assertRedirect(route('trips.index'));

    expect(Trip::query()->find($trip->id))->toBeNull();
});

test('users can toggle trip favorite', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create(['is_favorite' => false]);

    $this->actingAs($user)
        ->patch(route('trips.favorite', $trip))
        ->assertRedirect();

    expect($trip->fresh()->is_favorite)->toBeTrue();
});

test('trips index filters favorites', function () {
    $user = User::factory()->create();

    Trip::factory()->forUser($user)->create(['title' => 'Regular']);
    Trip::factory()->forUser($user)->favorite()->create(['title' => 'Favorite']);

    $this->actingAs($user)
        ->get(route('trips.index', ['filter' => 'favorites']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trips', 1)
            ->where('trips.0.title', 'Favorite'));
});

test('trip creation rejects start dates in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayload([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors(['start_date']);
});

test('trip creation rejects end date before start date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayload([
            'start_date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors(['end_date']);
});

test('updating material trip details clears a generated itinerary', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->withItinerary()->create([
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
        ],
    ]);

    $this->actingAs($user)
        ->put(route('trips.update', $trip), validTripPayload([
            'destination' => [
                'label' => 'Kyoto, Japan',
            ],
        ]))
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->status->value)->toBe('draft')
        ->and($trip->itinerary['days'])->toBe([])
        ->and($trip->itinerary['summary'])->toBe('');
});

test('updating non-material trip details keeps the itinerary', function () {
    $user = User::factory()->create();

    $basePayload = validTripPayload([
        'title' => 'Old Title',
        'destination' => [
            'label' => 'Tokyo, Japan',
        ],
    ]);

    $trip = Trip::factory()->forUser($user)->withItinerary()->create([
        'title' => $basePayload['title'],
        'type' => $basePayload['type'],
        'travelers' => $basePayload['travelers'],
        'start_date' => $basePayload['start_date'],
        'end_date' => $basePayload['end_date'],
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
        ],
    ]);

    $this->actingAs($user)
        ->put(route('trips.update', $trip), validTripPayload([
            'title' => 'New Title',
            'notes' => 'Updated notes only',
        ]))
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->title)->toBe('New Title')
        ->and($trip->status->value)->toBe('planned')
        ->and($trip->itinerary['days'])->toHaveCount(1)
        ->and($trip->itinerary['summary'])->toBe('Sample generated plan.');
});

test('dashboard shows trip stats', function () {
    $user = User::factory()->create();

    Trip::factory()->forUser($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.trips', 2)
            ->has('recentTrips', 2));
});
