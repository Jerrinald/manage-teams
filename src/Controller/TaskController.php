<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\CreateTaskInput;
use App\Dto\Input\UpdateTaskInput;
use App\Dto\Output\TaskView;
use App\Entity\Task;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Message\TaskAssignedMessage;
use App\Repository\TaskRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Security\Voter\TaskVoter;
use App\Security\Voter\TeamVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class TaskController extends AbstractController
{
    #[Route('/api/teams/{teamId}/tasks', methods: ['POST'])]
    public function create(
        #[MapEntity(id: 'teamId')] Team $team,
        #[MapRequestPayload] CreateTaskInput $input,
        UserRepository $userRepository,
        TeamMemberRepository $memberRepository,
        EntityManagerInterface $em,
        ObjectMapperInterface $objectMapper,
        MessageBusInterface $messageBus,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::EDIT, $team);

        $task = $objectMapper->map($input, Task::class);
        $task->setTeam($team);

        if ($input->assigneeId !== null) {
            $task->assignee = $this->resolveAssignee($input->assigneeId, $team, $userRepository, $memberRepository);
        }

        $em->persist($task);
        $em->flush();

        if ($task->assignee !== null) {
            $messageBus->dispatch(new TaskAssignedMessage($task->getId(), $task->assignee->getId()));
        }

        return $this->json($objectMapper->map($task, TaskView::class), 201);
    }

    #[Route('/api/teams/{teamId}/tasks', methods: ['GET'])]
    public function listByTeam(
        #[MapEntity(id: 'teamId')] Team $team,
        TaskRepository $taskRepository,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        $views = array_map(
            fn(Task $task) => $objectMapper->map($task, TaskView::class),
            $taskRepository->findByTeam($team),
        );

        return $this->json($views);
    }

    #[Route('/api/tasks/{id}', methods: ['GET'])]
    public function show(
        Task $task,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TaskVoter::VIEW, $task);

        return $this->json($objectMapper->map($task, TaskView::class));
    }

    #[Route('/api/tasks/{id}', methods: ['PATCH'])]
    public function update(
        Task $task,
        #[MapRequestPayload] UpdateTaskInput $input,
        UserRepository $userRepository,
        TeamMemberRepository $memberRepository,
        EntityManagerInterface $em,
        ObjectMapperInterface $objectMapper,
        MessageBusInterface $messageBus,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        $previousAssigneeId = $task->assignee?->getId();

        try {
            $objectMapper->map($input, $task);
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        if ($input->assigneeId !== null) {
            $task->assignee = $this->resolveAssignee($input->assigneeId, $task->getTeam(), $userRepository, $memberRepository);
        }

        $em->flush();

        $newAssigneeId = $task->assignee?->getId();
        if ($newAssigneeId !== null && (string) $newAssigneeId !== (string) $previousAssigneeId) {
            $messageBus->dispatch(new TaskAssignedMessage($task->getId(), $newAssigneeId));
        }

        return $this->json($objectMapper->map($task, TaskView::class));
    }

    #[Route('/api/tasks/{id}/status', methods: ['PATCH'])]
    public function updateStatus(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        ObjectMapperInterface $objectMapper,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TaskVoter::EDIT, $task);

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['status']) || !\is_string($payload['status'])) {
            throw new UnprocessableEntityHttpException('Missing "status" field.');
        }

        $next = TaskStatus::tryFrom($payload['status']);
        if ($next === null) {
            throw new UnprocessableEntityHttpException(sprintf('Invalid status "%s".', $payload['status']));
        }

        $this->applyStatus($task, $next);
        $em->flush();

        return $this->json($objectMapper->map($task, TaskView::class));
    }

    #[Route('/api/tasks/{id}', methods: ['DELETE'])]
    public function delete(
        Task $task,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(TaskVoter::DELETE, $task);

        $em->remove($task);
        $em->flush();

        return new JsonResponse(null, 204);
    }

    private function applyStatus(Task $task, TaskStatus $next): void
    {
        try {
            $task->status = $next;
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
    }

    private function resolveAssignee(
        string $assigneeId,
        Team $team,
        UserRepository $userRepository,
        TeamMemberRepository $memberRepository,
    ): User {
        $assignee = $userRepository->find(Uuid::fromString($assigneeId));
        if ($assignee === null) {
            throw new NotFoundHttpException('Assignee not found.');
        }
        if ($memberRepository->findOneByTeamAndUser($team, $assignee) === null) {
            throw new UnprocessableEntityHttpException('Assignee is not a member of this team.');
        }

        return $assignee;
    }
}
