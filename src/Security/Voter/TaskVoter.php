<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Task>
 */
final class TaskVoter extends Voter
{
    public const string VIEW = 'TASK_VIEW';
    public const string EDIT = 'TASK_EDIT';
    public const string DELETE = 'TASK_DELETE';

    public function __construct(
        private readonly TeamMemberRepository $memberRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Task
            && \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $member = $this->memberRepository->findOneByTeamAndUser($subject->getTeam(), $user);
        if ($member === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $member->role->canView(),
            self::EDIT => $member->role->canEditTasks(),
            self::DELETE => $member->role->canManageMembers(),
            default => false,
        };
    }
}
