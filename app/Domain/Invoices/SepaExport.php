<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

final class SepaExport
{
    public function __construct(
        public string $creditTransfers,
        public string $directDebit,
    ) {}
}
