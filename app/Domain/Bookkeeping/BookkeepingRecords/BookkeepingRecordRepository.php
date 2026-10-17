<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\BookkeepingRecords;

use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use JeroenG\Autowire\Attribute\Autowire;
use App\Domain\Bookkeeping\CostCenters\CostCenterYearResult;

#[Autowire]
interface BookkeepingRecordRepository
{
    public function createForBatch(SepaBatchId $batchId): void;

    public function createForPurchaseOrder(PurchaseOrderIdList $ids): void;

    public function createForInvoice(InvoiceIdList $ids): void;

    public function linkToBankTransaction(
        BankTransactionId $bankTransactionId,
        InvoiceIdList $invoiceIds,
        PurchaseOrderIdList $purchaseOrderIds,
    ): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
