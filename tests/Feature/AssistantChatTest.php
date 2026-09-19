<?php

use App\Contracts\Ai\TravelAssistant;
use App\Data\Ai\AssistantChatResponse;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantContextBuilder;

beforeEach(function () {
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        AssistantConversation::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }

    AssistantConversation::query()->whereNotNull('_id')->delete();
});

test('users can view the travel assistant page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('assistant.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assistant/Index')
            ->where('aiConfigured', false)
            ->has('conversations')
            ->where('activeConversation', null));
});

test('users can create a conversation and chat with the assistant', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(AssistantContextBuilder::class, function ($mock): void {
        $mock->shouldReceive('build')
            ->once()
            ->andReturn([
                'user_name' => 'Test User',
                'rag_context' => '',
                'rag_sources' => [],
            ]);
    });

    $this->mock(TravelAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn(new AssistantChatResponse(
                message: 'October is a great month for Rajasthan before peak winter crowds.',
            ));
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('assistant.conversations.store'))
        ->assertRedirect();

    $conversation = AssistantConversation::query()->where('user_id', $user->id)->first();

    expect($conversation)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('assistant.conversations.messages.store', $conversation), [
            'message' => 'When is the best time to visit Rajasthan?',
        ])
        ->assertRedirect();

    $conversation->refresh();

    expect($conversation->messages)->toHaveCount(2)
        ->and($conversation->messages[0]['role'])->toBe('user')
        ->and($conversation->messages[0]['content'])->toBe('When is the best time to visit Rajasthan?')
        ->and($conversation->messages[1]['role'])->toBe('assistant')
        ->and($conversation->messages[1]['content'])->toContain('Rajasthan')
        ->and($conversation->title)->toBe('When is the best time to visit Rajasthan?');
});

test('assistant chat stores rag source citations on assistant messages', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(AssistantContextBuilder::class, function ($mock): void {
        $mock->shouldReceive('build')
            ->once()
            ->andReturn([
                'user_name' => 'Test User',
                'rag_context' => 'Retrieved travel knowledge:',
                'rag_sources' => [
                    [
                        'document_id' => 'goa-doc',
                        'title' => 'Goa monsoon and beach guide',
                        'score' => 0.88,
                    ],
                ],
            ]);
    });

    $this->mock(TravelAssistant::class, function ($mock): void {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn(new AssistantChatResponse(
                message: 'South Goa is quieter during the monsoon.',
            ));
    });

    $user = User::factory()->create();
    $conversation = AssistantConversation::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('assistant.conversations.messages.store', $conversation), [
            'message' => 'Any monsoon tips for Goa?',
        ])
        ->assertRedirect();

    $conversation->refresh();

    expect($conversation->messages[1]['rag_sources'] ?? [])->toHaveCount(1)
        ->and($conversation->messages[1]['rag_sources'][0]['title'])
        ->toBe('Goa monsoon and beach guide');
});

test('users cannot access another users assistant conversation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $conversation = AssistantConversation::factory()->forUser($owner)->create();

    $this->actingAs($other)
        ->get(route('assistant.show', $conversation))
        ->assertForbidden();

    $this->actingAs($other)
        ->post(route('assistant.conversations.messages.store', $conversation), [
            'message' => 'Hello?',
        ])
        ->assertForbidden();

    $this->actingAs($other)
        ->delete(route('assistant.conversations.destroy', $conversation))
        ->assertForbidden();
});

test('assistant chat requires a configured gemini api key', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $user = User::factory()->create();
    $conversation = AssistantConversation::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->from(route('assistant.show', $conversation))
        ->post(route('assistant.conversations.messages.store', $conversation), [
            'message' => 'Hello?',
        ])
        ->assertSessionHasErrors('message');
});
