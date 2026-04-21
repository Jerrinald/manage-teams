<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BlockedTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tokens:cleanup',
    description: 'Purge les JTI expirés de la table blocked_tokens.',
)]
final class CleanupBlockedTokensCommand
{
    public function __construct(
        private readonly BlockedTokenRepository $repository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $deleted = $this->repository->deleteExpired(new \DateTimeImmutable());

        $io->success(sprintf('%d token(s) expiré(s) supprimé(s).', $deleted));

        return Command::SUCCESS;
    }
}
