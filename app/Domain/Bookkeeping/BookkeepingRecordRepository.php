<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BookkeepingRecordRepository
{
    /**
     * Create bookkeeping records for all pending invoices in the given batch.
     * Records are created per invoice per cost center, summing line subtotals.
     */
    public function createForBatch(InvoiceBatchId $batchId): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
