<?php

namespace App\Enums;

enum TripCollaboratorRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';

    /**
     * An accepted open-trip joiner. Read-only on the itinerary, hidden from
     * budget/notes/chat, and may only manage their own expense entries when
     * the trip's expense sheet is shared.
     */
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Viewer => 'Viewer',
            self::Editor => 'Editor',
            self::Member => 'Member',
        };
    }
}
