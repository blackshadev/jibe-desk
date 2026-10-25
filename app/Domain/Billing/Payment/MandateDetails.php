<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeInterface;

final readonly class MandateDetails
{
    public function __construct(
        public string $iban,
        public string $bic,
        public string $accountHolderName,
        public DateTimeInterface $signedAt,
    ) {}
}
