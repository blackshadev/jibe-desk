<?php

declare(strict_types=1);

namespace App\Domain\Mail;

final readonly class Recipient
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
