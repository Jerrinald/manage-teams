<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\SendTaskReminderMessage;
use App\Repository\TaskRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'tasks:remind',
    description: 'Dispatche un rappel pour chaque tâche en retard non terminée.',
)]
final class RemindOverdueTasksCommand
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $overdue = $this->taskRepository->findOverdue();

        if ($overdue === []) {
            $io->success('Aucune tâche en retard.');

            return Command::SUCCESS;
        }

        foreach ($overdue as $task) {
            $this->bus->dispatch(new SendTaskReminderMessage($task->getId()));
        }

        $io->success(sprintf('%d rappel(s) dispatché(s).', \count($overdue)));

        return Command::SUCCESS;
    }
}
