<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

final readonly class SepaConfiguration
{
    public function __construct(
        public string $creditorId,
        public string $creditorName,
        public string $creditorIban,
        public string $creditorBic,
    ) {}
}
