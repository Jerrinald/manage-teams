<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendTaskReminderMessage;
use App\Repository\TaskRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendTaskReminderHandler
{
    public function __construct(
        private TaskRepository $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendTaskReminderMessage $message): void
    {
        $task = $this->taskRepository->find($message->taskId);

        if ($task === null) {
            $this->logger->warning('SendTaskReminder: task not found', [
                'taskId' => (string) $message->taskId,
            ]);

            return;
        }

        $this->logger->info('[notification] Task reminder', [
            'taskId' => (string) $task->getId(),
            'title' => $task->title,
            'team' => $task->getTeam()->name,
            'dueDate' => $task->dueDate->format(\DateTimeInterface::ATOM),
            'assignee' => $task->assignee?->getEmail(),
            'status' => $task->status->value,
        ]);
    }
}
