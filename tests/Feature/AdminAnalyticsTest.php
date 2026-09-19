<?php

use App\Enums\AiUsageFeature;
use App\Models\AiUsageLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\Admin\AdminAnalytics;
use App\Services\Ai\AiUsageRecorder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('admin dashboard summarises trips and ai usage', function () {
    skipUnlessMongoDbAvailable();

    Cache::forget(AdminAnalytics::CACHE_KEY);
    Trip::query()->whereNotNull('_id')->delete();
    AiUsageLog::query()->whereNotNull('_id')->delete();

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    Trip::factory()->forUser($owner)->count(2)->create([
        'destination' => ['label' => 'Goa, India'],
    ]);
    Trip::factory()->forUser($owner)->road()->create([
        'destination' => ['label' => 'Manali, India'],
    ]);
    Trip::factory()->forUser($owner)->archived()->create();

    AiUsageLog::query()->create([
        'user_id' => $owner->id,
        'feature' => AiUsageFeature::TripChat->value,
        'model' => 'gemini-2.5-flash',
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'cost_usd' => 0.25,
    ]);
    AiUsageLog::query()->create([
        'user_id' => $owner->id,
        'feature' => AiUsageFeature::ItineraryGeneration->value,
        'model' => 'gemini-2.5-flash',
        'prompt_tokens' => 200,
        'completion_tokens' => 100,
        'total_tokens' => 300,
        'cost_usd' => 0.75,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('analytics.trips.total', 3)
            ->where('analytics.trips.vacation', 2)
            ->where('analytics.trips.road', 1)
            ->where('analytics.trips.top_destinations.0.label', 'Goa, India')
            ->where('analytics.trips.top_destinations.0.count', 2)
            ->has('analytics.trips.by_month', 6)
            ->where('analytics.ai.total_requests', 2)
            ->where('analytics.ai.total_tokens', 450)
            ->where('analytics.ai.total_cost_usd', 1)
            ->has('analytics.ai.daily', 30)
            ->has('analytics.ai.by_feature', 2)
            ->where('analytics.ai.top_users.0.email', $owner->email)
            ->where('analytics.ai.top_users.0.requests', 2));
});

test('regular users cannot see admin analytics', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('usage recorder estimates cost from token usage', function () {
    skipUnlessMongoDbAvailable();

    AiUsageLog::query()->whereNotNull('_id')->delete();
    Http::fake(['*' => Http::response([
        'usageMetadata' => [
            'promptTokenCount' => 1_000_000,
            'candidatesTokenCount' => 100_000,
            'totalTokenCount' => 1_100_000,
        ],
    ])]);

    $response = Http::get('https://example.test');
    expect($response)->toBeInstanceOf(Response::class);

    app(AiUsageRecorder::class)->record($response, 'gemini-2.5-flash', AiUsageFeature::TripChat, 7);

    $log = AiUsageLog::query()->firstOrFail();

    expect($log->user_id)->toBe(7)
        ->and($log->total_tokens)->toBe(1_100_000)
        ->and($log->cost_usd)->toBe(0.55);
});

test('usage recorder ignores responses without usage metadata', function () {
    skipUnlessMongoDbAvailable();

    AiUsageLog::query()->whereNotNull('_id')->delete();
    Http::fake(['*' => Http::response(['ok' => true])]);

    app(AiUsageRecorder::class)->record(Http::get('https://example.test'), 'gemini-2.5-flash', AiUsageFeature::Other, null);

    expect(AiUsageLog::query()->count())->toBe(0);
});
