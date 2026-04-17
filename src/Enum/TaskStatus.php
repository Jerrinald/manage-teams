<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::InProgress => 'En cours',
            self::Done => 'Terminé',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Todo => $next === self::InProgress,
            self::InProgress => $next === self::Done,
            self::Done => false,
        };
    }
}
