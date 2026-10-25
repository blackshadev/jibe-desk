<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use RuntimeException;

final class InvoicePaymentDeclinedMailUnavailableException extends RuntimeException
{
    private function __construct(
        public readonly InvoiceId $invoiceId,
        public readonly InvoicePaymentDeclinedMailUnavailableReason $reason,
    ) {
        parent::__construct(
            "Cannot create payment declined mail data for invoice {$invoiceId->value} ({$reason->value}).",
        );
    }

    public static function invoiceNotFound(InvoiceId $invoiceId): self
    {
        return new self($invoiceId, InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotFound);
    }

    public static function invoiceNotDeclined(InvoiceId $invoiceId): self
    {
        return new self($invoiceId, InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotDeclined);
    }

    public static function invoiceIsCreditInvoice(InvoiceId $invoiceId): self
    {
        return new self($invoiceId, InvoicePaymentDeclinedMailUnavailableReason::InvoiceIsCreditInvoice);
    }

    public static function recipientEmailMissing(InvoiceId $invoiceId): self
    {
        return new self($invoiceId, InvoicePaymentDeclinedMailUnavailableReason::RecipientEmailMissing);
    }
}
