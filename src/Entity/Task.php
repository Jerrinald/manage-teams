<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 200)]
    public string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(enumType: TaskStatus::class)]
    public TaskStatus $status {
        set(TaskStatus $next) {
            if (isset($this->status) && !$this->status->canTransitionTo($next)) {
                throw new \DomainException(sprintf(
                    'Transition invalide : %s → %s',
                    $this->status->value,
                    $next->value,
                ));
            }
            $this->status = $next;
        }
    }

    #[ORM\Column]
    public \DateTimeImmutable $dueDate {
        set(\DateTimeImmutable|string $value) {
            $this->dueDate = \is_string($value)
                ? new \DateTimeImmutable($value)
                : $value;
        }
    }

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?User $assignee = null;

    public string $fullTitle {
        get => strtoupper($this->title).' ['.$this->status->label().']';
    }

    public function __construct(string $title, \DateTimeImmutable|string $dueDate)
    {
        $this->id = new UuidV7();
        $this->title = $title;
        $this->dueDate = $dueDate;
        $this->status = TaskStatus::Todo;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;
    }
}
