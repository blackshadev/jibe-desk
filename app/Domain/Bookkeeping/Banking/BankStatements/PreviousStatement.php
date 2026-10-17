<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankStatements;

final readonly class PreviousStatement
{
    public function __construct(
        public string $statementNumber,
        public float $closingBalance,
    ) {}
}
