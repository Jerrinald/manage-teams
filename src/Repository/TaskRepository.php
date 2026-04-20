<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\Team;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /** @return list<Task> */
    public function findOverdue(\DateTimeImmutable $now = new \DateTimeImmutable()): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dueDate < :now')
            ->andWhere('t.status != :done')
            ->setParameter('now', $now)
            ->setParameter('done', TaskStatus::Done->value)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Task> */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.team = :team')
            ->setParameter('team', $team)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
