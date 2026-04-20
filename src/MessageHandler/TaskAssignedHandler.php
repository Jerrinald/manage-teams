<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TaskAssignedMessage;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TaskAssignedHandler
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TaskAssignedMessage $message): void
    {
        $task = $this->taskRepository->find($message->taskId);
        $assignee = $this->userRepository->find($message->assigneeId);

        if ($task === null || $assignee === null) {
            $this->logger->warning('TaskAssigned: task or assignee not found', [
                'taskId' => (string) $message->taskId,
                'assigneeId' => (string) $message->assigneeId,
            ]);

            return;
        }

        $this->logger->info('[notification] Task assigned', [
            'taskId' => (string) $task->getId(),
            'title' => $task->title,
            'team' => $task->getTeam()->name,
            'to' => $assignee->getEmail(),
        ]);
    }
}
