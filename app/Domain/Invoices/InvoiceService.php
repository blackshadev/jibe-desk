<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceService
{
    public function markAsPaid(InvoiceIdList $ids): void;
}
