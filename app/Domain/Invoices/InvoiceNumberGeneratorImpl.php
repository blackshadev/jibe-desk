<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use Psr\Clock\ClockInterface;
use Override;

final readonly class InvoiceNumberGeneratorImpl implements InvoiceNumberGenerator
{
    public function __construct(
        private InvoiceNumberRepository $invoiceRepository,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function generate(): InvoiceNumber
    {
        $year = $this->clock->now()->format('Y');

        $latestInvoiceNumber = $this->invoiceRepository->getLatestInvoiceNumber();

        if (!str_starts_with($latestInvoiceNumber, "I-{$year}")) {
            $latestInvoiceNumber = "I-{$year}000000";
        }

        $number = (int) mb_substr($latestInvoiceNumber, 6);
        $number++;

        return new InvoiceNumber("I-{$year}" . mb_str_pad((string) $number, 6, '0', STR_PAD_LEFT));
    }
}
