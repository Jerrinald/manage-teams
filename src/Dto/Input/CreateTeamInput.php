<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Team;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[Map(target: Team::class)]
class CreateTeamInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name;
}
