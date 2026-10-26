<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

final readonly class MandateCorrection
{
    public function __construct(
        public string $bic,
        public string $accountHolderName,
    ) {}
}
