<?php

declare(strict_types=1);

namespace App\Security\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class TeamScopedMember
{
    public function __construct(
        public readonly string $teamParam = 'teamId',
        public readonly string $memberParam = 'memberId',
    ) {}
}
