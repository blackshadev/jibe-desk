<?php

declare(strict_types=1);

namespace App\Domain\Members\Dto;

use DateTimeInterface;

final readonly class NewMemberPaymentInformation
{
    public function __construct(
        public string $iban,
        public string $bic,
        public string $accountHolderName,
        public DateTimeInterface $mandateAcceptedDate,
    ) {}
}
