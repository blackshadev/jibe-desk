<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Invoices\GenerateInvoice;
use App\Domain\Invoices\InvoiceGenerator;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-invoice {memberId} {date}')]
#[Description('Creates an invoice for a member')]
final class GenerateInvoiceCommand extends Command
{
    public function handle(InvoiceGenerator $invoiceGenerator): void
    {
        $command = new GenerateInvoice(
            memberId: MemberId::create((int) $this->argument('memberId')),
            invoiceDate: CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date')),
        );

        $invoiceGenerator->generate($command);
    }
}
