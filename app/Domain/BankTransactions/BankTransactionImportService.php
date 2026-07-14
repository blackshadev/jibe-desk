<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionImportService
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function importFromFile(string $filePath): array;
}
