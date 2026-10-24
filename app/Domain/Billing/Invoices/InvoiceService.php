<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceService
{
    public function createCredit(InvoiceId $originalInvoiceId): InvoiceId;

    public function markAsPaid(InvoiceIdList $ids): void;

    public function markAsDeclined(
        InvoiceIdList $ids,
        ?BankTransactionId $reversalTransactionId = null,
        ?string $note = null,
    ): void;

    public function reopen(InvoiceIdList $ids): void;

    public function restoreDeclined(InvoiceIdList $ids): void;

    public function markAsPending(InvoiceIdList $ids): void;
}
