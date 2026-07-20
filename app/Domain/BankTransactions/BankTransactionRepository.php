<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionRepository
{
    public function create(CreateBankTransaction $dto): BankTransactionId;

    public function existsByHash(string $hash): bool;

    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    public function attachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void;

    public function detachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void;

    public function getAttachedInvoiceIds(BankTransactionId $bankTransactionId): InvoiceIdList;

    public function getAttachedPurchaseOrderIds(BankTransactionId $bankTransactionId): PurchaseOrderIdList;

    public function complete(BankTransactionId $bankTransactionId): void;
}
