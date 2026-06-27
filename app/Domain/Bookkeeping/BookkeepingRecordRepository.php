<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
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
     * Create bookkeeping records for a single purchase order.
     * Records are created per cost center, summing line amounts.
     * Idempotent: skips if records already exist for this purchase order.
     */
    public function createForPurchaseOrder(PurchaseOrderId $id): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
