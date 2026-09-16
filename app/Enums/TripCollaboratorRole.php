<?php

namespace App\Enums;

enum TripCollaboratorRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Viewer => 'Viewer',
            self::Editor => 'Editor',
        };
    }
}
