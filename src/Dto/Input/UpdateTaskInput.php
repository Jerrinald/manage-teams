<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Task;
use App\Enum\TaskStatus;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[Map(target: Task::class)]
class UpdateTaskInput
{
    #[Map(if: [self::class, 'isNotNull'])]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    #[Map(if: [self::class, 'isNotNull'])]
    public ?string $description = null;

    #[Map(if: [self::class, 'isNotNull'])]
    public ?string $dueDate = null;

    #[Map(if: [self::class, 'isNotNull'])]
    #[Assert\Choice(callback: [TaskStatus::class, 'cases'])]
    public ?TaskStatus $status = null;

    #[Map(if: false)]
    #[Assert\Uuid]
    public ?string $assigneeId = null;

    public static function isNotNull(mixed $value): bool
    {
        return $value !== null;
    }
}
