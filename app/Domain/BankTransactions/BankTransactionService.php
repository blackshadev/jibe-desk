<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionService
{
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    public function complete(BankTransactionId $bankTransactionId): void;
}
