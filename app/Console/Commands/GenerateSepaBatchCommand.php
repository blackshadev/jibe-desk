<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Invoices\SepaBatch;
use App\Domain\Invoices\SepaBatchGenerator;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-sepa-batch {date?} {sepaTransferDate?}')]
#[Description('Generate an invoice batch for a given date')]
final class GenerateSepaBatchCommand extends Command
{
    public function handle(SepaBatchGenerator $sepaBatchGenerator): void
    {
        $date = $this->parseDate();

        $command = new SepaBatch(
            invoiceDate: $date,
            sepaTransferDate: $this->parseSepaTransferDate($date),
        );

        $sepaBatchGenerator->generate($command);
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
