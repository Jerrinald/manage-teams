<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\User;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: User::class)]
class UserView
{
    #[Map(source: 'id', transform: 'strval')]
    public string $id;

    public ?string $email;
}
