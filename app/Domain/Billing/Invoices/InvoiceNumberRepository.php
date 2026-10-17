<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceNumberRepository
{
    public function getLatestInvoiceNumber(): string;
}
