<?php

use App\Contracts\Ai\ChatAssistant;
use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\ChatResponse;
use App\Data\Ai\GeneratedItinerary;
use App\Models\Trip;
use App\Models\User;
use App\Services\Ai\GeminiUsageLimiter;
use Illuminate\Support\Facades\Cache;

function usageLimitTestItinerary(): GeneratedItinerary
{
    return new GeneratedItinerary(
        title: 'A quick trip',
        days: [],
        summary: 'A quick trip.',
    );
}

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    Cache::flush();

    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.daily_limit_per_user' => 2,
    ]);
});

test('usage limiter tracks and caps daily requests per user', function () {
    $user = User::factory()->create();
    $limiter = app(GeminiUsageLimiter::class);

    expect($limiter->hasRemaining($user))->toBeTrue()
        ->and($limiter->remaining($user))->toBe(2);

    $limiter->increment($user);

    expect($limiter->usedToday($user))->toBe(1)
        ->and($limiter->remaining($user))->toBe(1)
        ->and($limiter->hasRemaining($user))->toBeTrue();

    $limiter->increment($user);

    expect($limiter->remaining($user))->toBe(0)
        ->and($limiter->hasRemaining($user))->toBeFalse();
});

test('a daily limit of zero disables the guardrail', function () {
    config(['integrations.ai.daily_limit_per_user' => 0]);

    $user = User::factory()->create();
    $limiter = app(GeminiUsageLimiter::class);

    $limiter->increment($user);
    $limiter->increment($user);

    expect($limiter->hasRemaining($user))->toBeTrue()
        ->and($limiter->remaining($user))->toBeNull();
});

test('generating an itinerary counts against the daily gemini limit', function () {
    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->twice()->andReturn(usageLimitTestItinerary());
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)->post(route('trips.generate', $trip))->assertRedirect();
    $this->actingAs($user)->post(route('trips.generate', $trip))->assertRedirect();

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertRedirect()
        ->assertSessionHasErrors(['ai']);

    expect(session('errors')->get('ai')[0] ?? null)->toContain("today's AI usage limit");
});

test('trip chat counts against the daily gemini limit', function () {
    $this->mock(ChatAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')->twice()->andReturn(new ChatResponse(
            message: 'Sounds great!',
        ));
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)->post(route('trips.chat', $trip), ['message' => 'Hi'])->assertRedirect();
    $this->actingAs($user)->post(route('trips.chat', $trip), ['message' => 'Hi again'])->assertRedirect();

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), ['message' => 'One more?'])
        ->assertRedirect()
        ->assertSessionHasErrors(['chat']);
});

test('each user has their own independent daily gemini limit', function () {
    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->times(3)->andReturn(usageLimitTestItinerary());
    });

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $tripA = Trip::factory()->forUser($userA)->create();
    $tripB = Trip::factory()->forUser($userB)->create();

    $this->actingAs($userA)->post(route('trips.generate', $tripA))->assertRedirect();
    $this->actingAs($userA)->post(route('trips.generate', $tripA))->assertRedirect();

    $this->actingAs($userA)
        ->post(route('trips.generate', $tripA))
        ->assertSessionHasErrors(['ai']);

    $this->actingAs($userB)
        ->post(route('trips.generate', $tripB))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors(['ai']);
});
