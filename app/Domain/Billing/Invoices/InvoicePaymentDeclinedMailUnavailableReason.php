<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

enum InvoicePaymentDeclinedMailUnavailableReason: string
{
    case InvoiceNotFound = 'invoice_not_found';
    case InvoiceNotDeclined = 'invoice_not_declined';
    case InvoiceIsCreditInvoice = 'invoice_is_credit_invoice';
    case RecipientEmailMissing = 'recipient_email_missing';
}
