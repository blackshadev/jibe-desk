<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceRepository
{
    public function create(NewInvoice $invoice): InvoiceId;

    public function applyLines(ApplyInvoiceLines $invoice): AppliedInvoiceWithLineIds;

    public function markAsPaid(InvoiceIdList $ids): void;

    public function markAsDeclined(InvoiceIdList $ids): void;

    public function findMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date): ?InvoiceId;
}
