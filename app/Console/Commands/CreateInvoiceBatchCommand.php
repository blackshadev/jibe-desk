<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchGenerator;
use App\Domain\Invoices\InvoiceBatchId;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-invoice-batch {date}')]
#[Description('Creates an invoice for a member')]
final class CreateInvoiceBatchCommand extends Command
{
    public function handle(InvoiceBatchGenerator $invoiceBatchGenerator): void
    {
        $command = new InvoiceBatch(
            id: new InvoiceBatchId(1),
            invoiceDate: CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date')),
        );

        $invoiceBatchGenerator->generate($command);
    }
}
