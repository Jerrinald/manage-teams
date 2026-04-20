<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\Team;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

#[Map(target: Team::class)]
final class UpdateTeamInput
{
    public function __construct(
        #[Map(if: 'null !== $value')]
        #[Assert\Length(max: 100)]
        public readonly ?string $name = null,
    ) {}
}
