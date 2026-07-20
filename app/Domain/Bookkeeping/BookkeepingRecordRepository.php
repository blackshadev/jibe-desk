<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BookkeepingRecordRepository
{
    public function createForBatch(InvoiceBatchId $batchId): void;

    public function createForPurchaseOrder(PurchaseOrderIdList $ids): void;

    public function createForInvoice(InvoiceIdList $ids): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
