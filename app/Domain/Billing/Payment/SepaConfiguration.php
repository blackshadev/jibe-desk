<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

final readonly class SepaConfiguration
{
    public function __construct(
        public string $creditorId,
        public string $creditorName,
        public string $creditorIban,
        public string $creditorBic,
        public int $noticePeriodDays,
    ) {}
}
