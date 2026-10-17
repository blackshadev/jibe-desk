<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Jobs;

use App\Domain\Billing\Invoices\InvoiceGenerator;
use App\Domain\Billing\Invoices\InvoiceTarget as GenerateInvoiceEntity;
use App\Domain\Jobs\BaseJob;

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
