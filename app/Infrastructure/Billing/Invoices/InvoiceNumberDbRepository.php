<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceNumberRepository;
use App\Models\Invoice;
use Override;

final class InvoiceNumberDbRepository implements InvoiceNumberRepository
{
    #[Override]
    public function getLatestInvoiceNumber(): string
    {
        return Invoice::query()->max('invoice_number') ?? 'I-0000000000';
    }
}
