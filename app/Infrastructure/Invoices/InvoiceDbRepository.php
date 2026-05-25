<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceRepository;
use App\Models\Invoice;

final class InvoiceDbRepository implements InvoiceRepository
{
    public function getLatestInvoiceNumber(): string
    {
        return Invoice::query()->max('invoice_number') ?? 'I-0000000000';
    }
}
