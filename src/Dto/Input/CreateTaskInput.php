<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTaskInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $title;

    public ?string $description = null;

    #[Assert\NotBlank]
    public string $dueDate;

    /** UUID de l'assignee (nullable) — résolu en User par le controller */
    #[Assert\Uuid]
    public ?string $assigneeId = null;
}
