<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Invoices\InvoiceBatch;
use App\Domain\Invoices\InvoiceBatchGenerator;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-invoice-batch {date?} {sepaTransferDate?}')]
#[Description('Generate an invoice batch for a given date')]
final class GenerateInvoiceBatchCommand extends Command
{
    public function handle(InvoiceBatchGenerator $invoiceBatchGenerator): void
    {
        $date = $this->parseDate();

        $command = new InvoiceBatch(
            invoiceDate: $date,
            sepaTransferDate: $this->parseSepaTransferDate($date),
        );

        $invoiceBatchGenerator->generate($command);
    }

    private function parseDate(): DateTimeInterface
    {
        if ($this->argument('date')) {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->argument('date'));
        }

        return CarbonImmutable::now()->startOfDay();
    }

    private function parseSepaTransferDate(DateTimeInterface $invoiceDate): DateTimeInterface
    {
        if ($this->argument('sepaTransferDate')) {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->argument('sepaTransferDate'));
        }

        return CarbonImmutable::instance($invoiceDate)->addDays(14);
    }
}
