<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $items = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => self::present($notification));

        return Inertia::render('Notifications/Index', [
            'items' => $items,
            'filter' => $filter,
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * @return array{id: string, kind: string, title: string, message: string, url: mixed, read: bool, created_at: string|null}
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => isset($notification->data['days_until']) ? 'reminder' : 'shared',
            'title' => (string) ($notification->data['title'] ?? 'Notification'),
            'message' => (string) ($notification->data['message'] ?? ''),
            'url' => $notification->data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        if ($request->boolean('stay')) {
            return back();
        }

        $url = $record->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/')
            ? redirect()->to($url)
            : redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
