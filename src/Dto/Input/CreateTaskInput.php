<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Task;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[Map(target: Task::class)]
class CreateTaskInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $title;

    public ?string $description = null;

    #[Assert\NotBlank]
    public string $dueDate;

    #[Map(if: false)]
    #[Assert\Uuid]
    public ?string $assigneeId = null;
}
