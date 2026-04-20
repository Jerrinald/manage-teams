<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Enum\TeamRole;
use Symfony\Component\Validator\Constraints as Assert;

class AddTeamMemberInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $userId;

    public TeamRole $role = TeamRole::Member;
}
