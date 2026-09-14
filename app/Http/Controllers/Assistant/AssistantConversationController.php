<?php

namespace App\Http\Controllers\Assistant;

use App\Actions\Assistant\SendAssistantMessage;
use App\Exceptions\AiGenerationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\StoreAssistantMessageRequest;
use App\Models\AssistantConversation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssistantConversationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AssistantConversation::class);

        return Inertia::render('Assistant/Index', $this->pageProps(
            conversations: $this->conversationsForUser(),
            activeConversation: null,
        ));
    }

    public function show(AssistantConversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        return Inertia::render('Assistant/Index', $this->pageProps(
            conversations: $this->conversationsForUser(),
            activeConversation: $conversation->toFrontendArray(),
        ));
    }

    public function store(): RedirectResponse
    {
        $this->authorize('create', AssistantConversation::class);

        $conversation = AssistantConversation::query()->create([
            'user_id' => auth()->id(),
            'title' => 'New conversation',
            'messages' => [],
        ]);

        return to_route('assistant.show', $conversation);
    }

    public function storeMessage(
        StoreAssistantMessageRequest $request,
        AssistantConversation $conversation,
        SendAssistantMessage $sendAssistantMessage,
    ): RedirectResponse {
        $this->authorize('update', $conversation);

        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return back()->withErrors([
                'message' => __('AI assistant is not configured. Add GEMINI_API_KEY to your environment.'),
            ]);
        }

        try {
            $sendAssistantMessage($conversation, $request->user(), $request->validated('message'));
        } catch (AiGenerationException $exception) {
            return back()->withErrors([
                'message' => $exception->getMessage(),
            ]);
        }

        return back();
    }

    public function destroy(AssistantConversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return to_route('assistant.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationsForUser(): array
    {
        return AssistantConversation::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (AssistantConversation $conversation): array => [
                'id' => (string) $conversation->id,
                'title' => (string) $conversation->title,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $conversations
     * @param  array<string, mixed>|null  $activeConversation
     * @return array<string, mixed>
     */
    private function pageProps(array $conversations, ?array $activeConversation): array
    {
        return [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'aiConfigured' => filled(config('integrations.ai.drivers.gemini.api_key')),
        ];
    }
}
