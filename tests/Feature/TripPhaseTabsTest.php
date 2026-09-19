<?php

use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    $this->user = User::factory()->create();

    $make = fn (string $title, ?string $start, ?string $end, bool $road = false) => tap(
        Trip::factory()->forUser($this->user)->when($road, fn ($factory) => $factory->road())->make([
            'title' => $title,
            'start_date' => $start,
            'end_date' => $end,
        ]),
        fn (Trip $trip) => $trip->save(),
    );

    $make('Future', now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString());
    $make('Undated', null, null);
    $make('Running', now()->subDays(2)->toDateString(), now()->addDays(2)->toDateString());
    $make('Ends today', now()->subDays(3)->toDateString(), now()->toDateString());
    $make('Starts today', now()->toDateString(), null);
    $make('Finished', now()->subDays(10)->toDateString(), now()->subDays(5)->toDateString());
    $make('Single day past', now()->subDays(20)->toDateString(), null);
    $make('Road future', now()->addDays(5)->toDateString(), now()->addDays(6)->toDateString(), road: true);
    $make('Road past', now()->subDays(9)->toDateString(), now()->subDays(8)->toDateString(), road: true);
});

test('trips index defaults to upcoming and reports counts for every tab', function () {
    $this->actingAs($this->user)->get(route('trips.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('phase', 'upcoming')
            ->where('phaseCounts', ['upcoming' => 3, 'ongoing' => 3, 'past' => 3])
            ->has('trips.data', 3)
            ->where('trips.data.1.title', 'Road future')
            ->where('trips.data.2.title', 'Future'));
});

test('trips index lists ongoing and past trips', function () {
    $this->actingAs($this->user)->get(route('trips.index', ['phase' => 'ongoing']))
        ->assertInertia(fn ($page) => $page
            ->where('phase', 'ongoing')
            ->has('trips.data', 3));

    $this->get(route('trips.index', ['phase' => 'past']))
        ->assertInertia(fn ($page) => $page
            ->has('trips.data', 3)
            ->where('trips.data.0.title', 'Finished')
            ->where('trips.data.1.title', 'Road past')
            ->where('trips.data.2.title', 'Single day past'));
});

test('an unknown phase falls back to upcoming', function () {
    $this->actingAs($this->user)->get(route('trips.index', ['phase' => 'bogus']))
        ->assertInertia(fn ($page) => $page->where('phase', 'upcoming'));
});

test('trips are paginated', function () {
    Trip::factory()->forUser($this->user)->count(10)->create([
        'start_date' => now()->addDays(40)->toDateString(),
        'end_date' => now()->addDays(41)->toDateString(),
    ]);

    $this->actingAs($this->user)->get(route('trips.index'))
        ->assertInertia(fn ($page) => $page
            ->has('trips.data', 9)
            ->where('trips.total', 13)
            ->where('trips.last_page', 2));

    $this->get(route('trips.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('trips.data', 4));
});

test('road trips are split by phase', function () {
    $this->actingAs($this->user)->get(route('road-trips.index'))
        ->assertInertia(fn ($page) => $page
            ->where('phaseCounts', ['upcoming' => 1, 'ongoing' => 0, 'past' => 1])
            ->has('trips.data', 1)
            ->where('trips.data.0.title', 'Road future'));

    $this->get(route('road-trips.index', ['phase' => 'past']))
        ->assertInertia(fn ($page) => $page->where('trips.data.0.title', 'Road past'));
});
