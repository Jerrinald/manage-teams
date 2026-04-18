<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Team>
 */
final class TeamVoter extends Voter
{
    public const string VIEW = 'TEAM_VIEW';
    public const string EDIT = 'TEAM_EDIT';
    public const string MANAGE_MEMBERS = 'TEAM_MANAGE_MEMBERS';

    public function __construct(
        private readonly TeamMemberRepository $memberRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Team
            && \in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE_MEMBERS], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $member = $this->memberRepository->findOneByTeamAndUser($subject, $user);
        if ($member === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $member->role->canView(),
            self::EDIT => $member->role->canEditTasks(),
            self::MANAGE_MEMBERS => $member->role->canManageMembers(),
            default => false,
        };
    }
}
