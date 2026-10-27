<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceRepository
{
    public function create(NewInvoice $invoice): InvoiceId;

    public function applyLines(ApplyInvoiceLines $invoice): AppliedInvoiceWithLineIds;

    public function createCredit(InvoiceId $originalInvoiceId): InvoiceId;

    public function markAsPaid(InvoiceIdList $ids): void;

    public function markAsDeclined(
        InvoiceIdList $ids,
        ?BankTransactionId $reversalTransactionId = null,
        ?string $note = null,
    ): InvoiceIdList;

    public function reopen(InvoiceIdList $ids): void;

    public function markAsPendingIfDeclined(InvoiceIdList $ids): void;

    /** Returns the ids that are still pending after settlement. */
    public function markAsPending(InvoiceIdList $ids): InvoiceIdList;

    public function findMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date): ?InvoiceId;
}
