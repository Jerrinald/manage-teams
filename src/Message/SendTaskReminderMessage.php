<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class SendTaskReminderMessage
{
    public function __construct(
        public Uuid $taskId,
    ) {
    }
}
