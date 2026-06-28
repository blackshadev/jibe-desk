<?php

declare(strict_types=1);

namespace App\Domain\Mail;

final readonly class FinancialAdministrationRecipient
{
    public function __construct(
        public Recipient $recipient,
    ) {}
}
