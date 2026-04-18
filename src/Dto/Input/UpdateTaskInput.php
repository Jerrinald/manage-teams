<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateTaskInput
{
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    public ?string $description = null;

    public ?string $dueDate = null;

    #[Assert\Choice(callback: [TaskStatus::class, 'cases'])]
    public ?TaskStatus $status = null;

    #[Assert\Uuid]
    public ?string $assigneeId = null;
}
