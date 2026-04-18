<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Task;
use App\Enum\TaskStatus;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Task::class)]
class TaskView
{
    #[Map(source: 'id', transform: 'strval')]
    public string $id;

    public string $title;

    public ?string $description;

    #[Map(source: 'status', transform: [self::class, 'formatStatus'])]
    public string $status;

    #[Map(source: 'status', transform: [self::class, 'formatStatusLabel'])]
    public string $statusLabel;

    #[Map(source: 'dueDate', transform: [self::class, 'formatDate'])]
    public string $dueDate;

    #[Map(source: 'assignee', transform: [self::class, 'formatAssignee'])]
    public ?string $assigneeId;

    #[Map(source: 'team', transform: [self::class, 'formatTeam'])]
    public string $teamId;

    public static function formatStatus(TaskStatus $status): string
    {
        return $status->value;
    }

    public static function formatStatusLabel(TaskStatus $status): string
    {
        return $status->label();
    }

    public static function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('c');
    }

    public static function formatAssignee(?object $assignee): ?string
    {
        return $assignee?->getId()?->toRfc4122();
    }

    public static function formatTeam(object $team): string
    {
        return $team->getId()->toRfc4122();
    }
}
