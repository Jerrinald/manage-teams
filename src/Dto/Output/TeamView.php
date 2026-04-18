<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Team;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Team::class)]
class TeamView
{
    #[Map(source: 'id', transform: 'strval')]
    public string $id;

    public string $name;

    #[Map(source: 'createdAt', transform: [self::class, 'formatDate'])]
    public string $createdAt;

    public static function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('c');
    }
}
