<?php

use App\Contracts\Ai\EmbeddingService;
use App\Models\KnowledgeDocument;
use App\Models\User;

beforeEach(function () {
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        KnowledgeDocument::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }

    KnowledgeDocument::query()->whereNotNull('_id')->delete();
});

test('admins can manage knowledge documents', function () {
    config(['integrations.ai.drivers.gemini.api_key' => 'test-key']);

    $this->mock(EmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embed')->andReturn([1.0, 0.0, 0.0]);
    });

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.knowledge.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/knowledge/Index'));

    $this->actingAs($admin)
        ->post(route('admin.knowledge.store'), [
            'title' => 'Goa tips',
            'destinations' => 'goa, panaji',
            'content' => 'Monsoon beaches need caution.',
            'status' => 'published',
        ])
        ->assertRedirect();

    $document = KnowledgeDocument::query()->where('title', 'Goa tips')->first();

    expect($document)->not->toBeNull()
        ->and($document->destinations)->toContain('goa')
        ->and($document->chunk_count)->toBeGreaterThan(0);

    $this->actingAs($admin)
        ->put(route('admin.knowledge.update', $document), [
            'title' => 'Goa travel tips',
            'destinations' => 'goa',
            'content' => 'Updated monsoon guidance for Goa beaches.',
            'status' => 'published',
        ])
        ->assertRedirect(route('admin.knowledge.edit', $document));

    $document->refresh();

    expect($document->title)->toBe('Goa travel tips');

    $this->actingAs($admin)
        ->delete(route('admin.knowledge.destroy', $document))
        ->assertRedirect(route('admin.knowledge.index'));

    expect(KnowledgeDocument::query()->where('_id', $document->id)->exists())->toBeFalse();
});

test('regular users cannot access knowledge admin routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.knowledge.index'))
        ->assertForbidden();
});
