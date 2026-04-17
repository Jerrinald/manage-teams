<?php

declare(strict_types=1);

namespace App\Enum;

enum TeamRole: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Member => 'Membre',
            self::Guest => 'Invité',
        };
    }

    public function canManageMembers(): bool
    {
        return $this === self::Admin;
    }

    public function canEditTasks(): bool
    {
        return $this === self::Admin || $this === self::Member;
    }

    public function canView(): bool
    {
        return true;
    }
}
