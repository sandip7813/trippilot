<?php

use App\Contracts\Ai\ChatAssistant;
use App\Data\Ai\ChatResponse;
use App\Models\Trip;
use App\Models\User;

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

test('users can chat about their trip and store messages', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(ChatAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: 'Try visiting the local market on Day 2.',
            ));
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'itinerary' => [
            'days' => [
                ['day' => 1, 'title' => 'Arrival', 'activities' => []],
            ],
            'summary' => 'A quick getaway.',
            'packing_list' => [],
            'budget_breakdown' => [],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), [
            'message' => 'Any ideas for Day 2?',
        ])
        ->assertRedirect();

    $trip->refresh();

    expect($trip->chat_messages)->toHaveCount(2)
        ->and($trip->chat_messages[0]['role'])->toBe('user')
        ->and($trip->chat_messages[0]['content'])->toBe('Any ideas for Day 2?')
        ->and($trip->chat_messages[1]['role'])->toBe('assistant')
        ->and($trip->chat_messages[1]['content'])->toContain('Day 2');
});

test('chat applies itinerary patches from assistant responses', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(ChatAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: 'I updated Day 2 with a market visit.',
                patch: [
                    'itinerary' => [
                        'days' => [
                            [
                                'day' => 2,
                                'title' => 'Market day',
                                'activities' => [
                                    ['time' => '10:00', 'title' => 'Local market'],
                                ],
                            ],
                        ],
                    ],
                ],
            ));
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'itinerary' => [
            'days' => [
                ['day' => 1, 'title' => 'Arrival', 'activities' => []],
            ],
            'summary' => 'Original summary',
            'packing_list' => [],
            'budget_breakdown' => [],
        ],
    ]);

    $this->actingAs($user)
        ->from(route('trips.show', $trip))
        ->post(route('trips.chat', $trip), [
            'message' => 'Add a market visit on Day 2.',
        ])
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->itinerary['days'])->toHaveCount(2)
        ->and($trip->itinerary['days'][1]['title'])->toBe('Market day')
        ->and($trip->chat_messages[1]['patch_applied'] ?? false)->toBeTrue();
});

test('chat requires ai configuration', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), [
            'message' => 'Hello',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['ai']);
});

test('users cannot chat on another users trip', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->post(route('trips.chat', $trip), [
            'message' => 'Hello',
        ])
        ->assertForbidden();
});

test('chat validates message input', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), [
            'message' => '',
        ])
        ->assertSessionHasErrors(['message']);
});

test('trip show page exposes chat messages', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'chat_messages' => [
            [
                'id' => 'msg-1',
                'role' => 'user',
                'content' => 'Hello',
                'created_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->where('aiConfigured', true)
            ->has('trip.chat_messages', 1));
});

test('users can chat on their road trip', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(ChatAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')
            ->once()
            ->withArgs(function (string $message, array $history, array $tripContext): bool {
                return $message === 'How long should we drive each day?'
                    && ($tripContext['type'] ?? null) === 'road'
                    && ($tripContext['has_route'] ?? false) === true;
            })
            ->andReturn(new ChatResponse(
                message: 'Aim for about 6 hours of driving per day on this route.',
            ));
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->road()->create([
        'route' => [
            'distance_km' => 450,
            'duration_seconds' => 18000,
            'has_tolls' => true,
            'polyline' => [[19.076, 72.8777], [15.2993, 74.124]],
        ],
        'stops' => [
            ['label' => 'Lonavala lookout', 'lat' => 18.75, 'lng' => 73.4],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), [
            'message' => 'How long should we drive each day?',
        ])
        ->assertRedirect();

    $trip->refresh();

    expect($trip->chat_messages)->toHaveCount(2)
        ->and($trip->chat_messages[1]['content'])->toContain('6 hours');
});

test('road trip show page exposes ai configuration and chat messages', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->road()->create([
        'chat_messages' => [
            [
                'id' => 'msg-road-1',
                'role' => 'user',
                'content' => 'Any toll tips?',
                'created_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('road-trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('RoadTrips/Show')
            ->where('aiConfigured', true)
            ->has('trip.chat_messages', 1));
});
