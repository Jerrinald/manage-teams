<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\AddTeamMemberInput;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Security\Attribute\TeamScopedMember;
use App\Security\Voter\TeamVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/teams/{teamId}/members')]
final class MemberController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function add(
        #[MapEntity(id: 'teamId')] Team $team,
        #[MapRequestPayload] AddTeamMemberInput $input,
        UserRepository $userRepository,
        TeamMemberRepository $memberRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $team);

        $user = $userRepository->find(Uuid::fromString($input->userId));
        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        if ($memberRepository->findOneByTeamAndUser($team, $user) !== null) {
            throw new ConflictHttpException('User is already a member of this team.');
        }

        $member = new TeamMember($team, $user, $input->role);
        $em->persist($member);
        $em->flush();

        return $this->json([
            'id' => $member->getId()->toRfc4122(),
            'userId' => $user->getId()->toRfc4122(),
            'role' => $member->role->value,
            'joinedAt' => $member->getJoinedAt()->format('c'),
        ], 201);
    }

    #[Route('/{memberId}', methods: ['DELETE'])]
    public function remove(
        #[MapEntity(id: 'teamId')] Team $team,
        #[TeamScopedMember] TeamMember $member,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $team);

        $em->remove($member);
        $em->flush();

        return new JsonResponse(null, 204);
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapEntity(id: 'teamId')] Team $team,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        $members = $team->getMembers()->map(fn(TeamMember $member) => [
            'id' => $member->getId()->toRfc4122(),
            'userId' => $member->getUser()->getId()->toRfc4122(),
            'email' => $member->getUser()->getEmail(),
            'role' => $member->role->value,
            'joinedAt' => $member->getJoinedAt()->format('c'),
        ])->toArray();

        return $this->json(array_values($members));
    }
}
