<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankTransactions;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionService
{
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    public function complete(BankTransactionId $bankTransactionId): void;

    public function resolveMatching(BankTransactionIdList $ids): void;

    public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void;

    public function unlinkReversal(BankTransactionId $reversalId): void;

    public function linkInternalTransfer(BankTransactionId $a, BankTransactionId $b): void;

    public function unlinkInternalTransfer(BankTransactionId $id): void;
}
