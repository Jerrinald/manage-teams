<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\CreateTeamInput;
use App\Dto\Input\UpdateTeamInput;
use App\Dto\Output\TeamView;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use App\Repository\TeamRepository;
use App\Security\Voter\TeamVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/teams')]
final class TeamController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateTeamInput $input,
        #[CurrentUser] User $user,
        ObjectMapperInterface $objectMapper,
        EntityManagerInterface $em,
    ): JsonResponse {
        $team = $objectMapper->map($input, Team::class);

        // Le créateur devient Admin de l'équipe
        $member = new TeamMember($team, $user, TeamRole::Admin);
        $em->persist($team);
        $em->persist($member);
        $em->flush();

        return $this->json($objectMapper->map($team, TeamView::class), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        TeamRepository $teamRepository,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $teams = $teamRepository->findByUser($user);

        $views = array_map(
            fn(Team $team) => $objectMapper->map($team, TeamView::class),
            $teams,
        );

        return $this->json($views);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(
        Team $team,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        return $this->json($objectMapper->map($team, TeamView::class));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        Team $team,
        #[MapRequestPayload] UpdateTeamInput $input,
        EntityManagerInterface $em,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::EDIT, $team);

        $objectMapper->map($input, $team);
        $em->flush();

        return $this->json($objectMapper->map($team, TeamView::class));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        Team $team,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $team);

        $em->remove($team);
        $em->flush();

        return new JsonResponse(null, 204);
    }
}
