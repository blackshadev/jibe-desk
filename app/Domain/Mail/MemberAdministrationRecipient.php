<?php

declare(strict_types=1);

namespace App\Domain\Mail;

final readonly class MemberAdministrationRecipient
{
    public function __construct(
        public Recipient $recipient,
    ) {}
}
