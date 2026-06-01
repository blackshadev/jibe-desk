<?php

declare(strict_types=1);

namespace App\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceNumberRepository;
use App\Models\Invoice;

final class InvoiceNumberDbRepository implements InvoiceNumberRepository
{
    public function getLatestInvoiceNumber(): string
    {
        return Invoice::query()->max('invoice_number') ?? 'I-0000000000';
    }
}
