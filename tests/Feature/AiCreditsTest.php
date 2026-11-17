<?php

use App\Contracts\Ai\ChatAssistant;
use App\Contracts\Ai\TravelAssistant;
use App\Contracts\Ai\TripGenerator;
use App\Data\Ai\AssistantChatResponse;
use App\Data\Ai\ChatResponse;
use App\Data\Ai\GeneratedItinerary;
use App\Enums\CreditService;
use App\Enums\CreditTransactionType;
use App\Enums\TripCollaboratorRole;
use App\Exceptions\AiGenerationException;
use App\Models\AssistantConversation;
use App\Models\CreditTransaction;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\AssistantContextBuilder;
use App\Services\Credits\CreditLedger;
use App\Services\RoadTrips\RoadTripBreakSuggestionService;
use Illuminate\Support\Facades\Cache;

function creditsTestItinerary(): GeneratedItinerary
{
    return new GeneratedItinerary(
        title: 'A quick trip',
        days: [],
        summary: 'A quick trip.',
    );
}

function userWithCredits(int $credits): User
{
    $user = User::factory()->create();

    app(CreditLedger::class)->grant($user, $credits);

    return $user->fresh();
}

beforeEach(function () {
    Cache::flush();

    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'trippilot.credits.enabled' => true,
        'trippilot.credits.signup_bonus' => 30,
    ]);
});

test('generating an itinerary charges the user who triggers it', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(creditsTestItinerary());
    });

    $owner = userWithCredits(50);
    $collaborator = userWithCredits(20);
    $trip = Trip::factory()->forUser($owner)->withCollaborator($collaborator, TripCollaboratorRole::Editor)->create();

    $this->actingAs($collaborator)
        ->post(route('trips.generate', $trip))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($collaborator->fresh()->credit_balance)->toBe(8)
        ->and($owner->fresh()->credit_balance)->toBe(50);

    $spend = $collaborator->creditTransactions()->where('type', CreditTransactionType::Spend)->sole();

    expect($spend->amount)->toBe(-12)
        ->and($spend->service)->toBe(CreditService::ItineraryGeneration)
        ->and($spend->trip_id)->toBe((string) $trip->id);
});

test('regenerating an existing itinerary costs the regeneration price', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(creditsTestItinerary());
    });

    $user = userWithCredits(15);
    $trip = Trip::factory()->forUser($user)->withItinerary()->create();

    $this->actingAs($user)->post(route('trips.generate', $trip))->assertSessionHasNoErrors();

    expect($user->fresh()->credit_balance)->toBe(5);
});

test('credits are refunded when the ai request fails', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andThrow(new AiGenerationException('Gemini is unavailable.'));
    });

    $user = userWithCredits(20);
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertSessionHasErrors(['ai' => 'Gemini is unavailable.']);

    $refund = $user->creditTransactions()->where('type', CreditTransactionType::Refund)->sole();

    expect($user->fresh()->credit_balance)->toBe(20)
        ->and($refund->amount)->toBe(12)
        ->and($refund->related_transaction_id)
        ->toBe($user->creditTransactions()->where('type', CreditTransactionType::Spend)->sole()->id);
});

test('ai features are blocked without calling the ai when the balance is too low', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldNotReceive('generate');
    });

    $user = userWithCredits(5);
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.generate', $trip))
        ->assertSessionHasErrors(['ai']);

    expect(session('errors')->get('ai')[0])->toContain('Not enough credits: this needs 12 credits and you have 5')
        ->and($user->fresh()->credit_balance)->toBe(5)
        ->and($user->creditTransactions()->count())->toBe(1);
});

test('admins use ai features without spending credits', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(creditsTestItinerary());
    });

    $admin = User::factory()->admin()->create();
    $trip = Trip::factory()->forUser($admin)->create();

    $this->actingAs($admin)->post(route('trips.generate', $trip))->assertSessionHasNoErrors();

    expect($admin->creditTransactions()->count())->toBe(0);
});

test('nothing is charged while credits are switched off', function () {
    skipUnlessMongoDbAvailable();

    config(['trippilot.credits.enabled' => false]);

    $this->mock(TripGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn(creditsTestItinerary());
    });

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)->post(route('trips.generate', $trip))->assertSessionHasNoErrors();

    expect($user->creditTransactions()->count())->toBe(0);
});

test('a trip chat message costs the sender two credits', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(ChatAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')->once()->andReturn(new ChatResponse(message: 'Sounds great!'));
    });

    $user = userWithCredits(10);
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('trips.chat', $trip), ['message' => 'Add a beach day'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->credit_balance)->toBe(8);
});

test('a travel assistant message costs the sender two credits', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(AssistantContextBuilder::class, function ($mock): void {
        $mock->shouldReceive('build')->once()->andReturn(['rag_context' => '', 'rag_sources' => []]);
    });

    $this->mock(TravelAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')->once()->andReturn(new AssistantChatResponse(message: 'Try Jaipur in October.'));
    });

    $user = userWithCredits(10);
    $conversation = AssistantConversation::query()->create(['user_id' => $user->id, 'messages' => []]);

    $this->actingAs($user)
        ->post(route('assistant.conversations.messages.store', $conversation), ['message' => 'Where should I go?'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->credit_balance)->toBe(8);
});

test('road trip break suggestions cost three credits', function () {
    skipUnlessMongoDbAvailable();

    $this->mock(RoadTripBreakSuggestionService::class, function ($mock): void {
        $mock->shouldReceive('suggest')->once()->andReturn([]);
    });

    $user = userWithCredits(10);
    $trip = Trip::factory()->forUser($user)->road()->create([
        'route' => ['polyline' => [[19.076, 72.8777], [18.5204, 73.8567]], 'distance_km' => 150],
    ]);

    $this->actingAs($user)
        ->post(route('road-trips.suggest-breaks', $trip))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->credit_balance)->toBe(7);
});

test('a spend is only refunded once', function () {
    $user = userWithCredits(20);
    $ledger = app(CreditLedger::class);

    $spend = $ledger->spend($user, CreditService::TripChatMessage, 2);
    $ledger->refund($spend);
    $ledger->refund($spend);

    expect($user->fresh()->credit_balance)->toBe(20)
        ->and($user->creditTransactions()->where('type', CreditTransactionType::Refund)->count())->toBe(1);
});

test('verifying the email by replacing the one-time password grants the welcome bonus once', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'N3w-Secure-Passw0rd!',
            'password_confirmation' => 'N3w-Secure-Passw0rd!',
        ])
        ->assertRedirect(route('dashboard'));

    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->put(route('password.change.update'), [
        'password' => 'An0ther-Secure-Passw0rd!',
        'password_confirmation' => 'An0ther-Secure-Passw0rd!',
    ]);

    expect($user->fresh()->credit_balance)->toBe(30)
        ->and($user->creditTransactions()->where('type', CreditTransactionType::SignupBonus)->count())->toBe(1);
});

test('the backfill command gives existing verified users the welcome bonus once', function () {
    $verifiedUser = User::factory()->create();
    $unverifiedUser = User::factory()->unverified()->create();
    $admin = User::factory()->admin()->create();

    $this->artisan('trippilot:grant-signup-bonus')->assertSuccessful();
    $this->artisan('trippilot:grant-signup-bonus')->assertSuccessful();

    expect($verifiedUser->fresh()->credit_balance)->toBe(30)
        ->and($unverifiedUser->fresh()->credit_balance)->toBe(0)
        ->and($admin->fresh()->credit_balance)->toBe(0)
        ->and(CreditTransaction::query()->count())->toBe(1);
});

test('users can see what each AI service costs', function () {
    $user = userWithCredits(25);

    $this->actingAs($user)
        ->get(route('credits.costs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('credits/Costs')
            ->where('balance', 25)
            ->where('costs.0', [
                'service' => 'itinerary_generation',
                'label' => 'Itinerary generation',
                'credits' => 12,
            ])
            ->has('costs', count(CreditService::cases())));
});

test('users can see their credit history', function () {
    $user = userWithCredits(25);

    $this->actingAs($user)
        ->get(route('credits.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('credits/History')
            ->where('balance', 25)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 25));
});

test('the old settings credits address redirects to the credit history', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/credits')
        ->assertRedirect('/credits/history');
});

test('only users who spend credits receive the shared credits prop', function () {
    $user = userWithCredits(25);
    $admin = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('credits.history'))
        ->assertInertia(fn ($page) => $page
            ->where('credits.balance', 25)
            ->where('credits.costs.trip_chat_message', 2));

    $this->actingAs($admin)
        ->get(route('credits.history'))
        ->assertInertia(fn ($page) => $page->where('credits', null));
});
