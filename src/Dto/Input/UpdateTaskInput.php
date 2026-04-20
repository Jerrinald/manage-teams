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
    #[Map(if: 'null !== $value')]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    #[Map(if: 'null !== $value')]
    public ?string $description = null;

    #[Map(if: 'null !== $value')]
    public ?string $dueDate = null;

    #[Map(if: 'null !== $value')]
    #[Assert\Choice(callback: [TaskStatus::class, 'cases'])]
    public ?TaskStatus $status = null;

    #[Map(if: false)]
    #[Assert\Uuid]
    public ?string $assigneeId = null;
}
