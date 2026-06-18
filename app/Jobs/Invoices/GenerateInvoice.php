<?php

declare(strict_types=1);

namespace App\Jobs\Invoices;

use App\Domain\Invoices\InvoiceGenerator;
use App\Domain\Invoices\InvoiceTarget as GenerateInvoiceEntity;
use App\Jobs\BaseJob;

final class GenerateInvoice extends BaseJob
{
    public function __construct(
        private readonly GenerateInvoiceEntity $generateInvoice,
    ) {}

    public function handle(InvoiceGenerator $generator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $generator->generate($this->generateInvoice);
    }
}
