<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class InvoiceMailData
{
    /**
     * @param list<InvoiceMailLine> $lines
     */
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public string $memberName,
        public string $memberEmail,
        public DateTimeInterface $invoiceDate,
        public CompoundPrice $total,
        public array $lines,
        public ?DateTimeInterface $sepaTransferDate,
    ) {}
}
