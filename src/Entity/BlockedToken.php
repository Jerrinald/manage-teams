<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\BlockedTokenRepository;

#[ORM\Entity(repositoryClass: BlockedTokenRepository::class)]
#[ORM\Table(name: 'blocked_tokens')]
#[ORM\Index(columns: ['jti'])]
class BlockedToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $jti;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $jti, \DateTimeImmutable $expiresAt)
    {
        $this->jti = $jti;
        $this->expiresAt = $expiresAt;
    }

    public function getJti(): string { return $this->jti; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
}