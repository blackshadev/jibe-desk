<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceMailRepository
{
    public function getInvoiceMailData(InvoiceId $id): InvoiceMailData;

    public function isSettled(InvoiceId $id): bool;

    /** @throws InvoicePaymentDeclinedMailUnavailableException */
    public function getInvoicePaymentDeclinedMailData(InvoiceId $id): InvoicePaymentDeclinedMailData;

    public function markInvoiceAsSent(InvoiceId $id): void;
}
