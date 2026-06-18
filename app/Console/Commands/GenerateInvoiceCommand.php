<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Invoices\InvoiceGenerator;
use App\Domain\Invoices\InvoiceTarget;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-invoice {memberId} {date}')]
#[Description('Generate an invoice for a member')]
final class GenerateInvoiceCommand extends Command
{
    public function handle(InvoiceGenerator $invoiceGenerator): void
    {
        $command = new InvoiceTarget(
            memberId: MemberId::create((int) $this->argument('memberId')),
            invoiceDate: CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date')),
        );

        $invoiceGenerator->generate($command);
    }
}
