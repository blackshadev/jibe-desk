<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Mail\Recipient;
use DateTimeInterface;

final readonly class InvoiceMailData
{
    /**
     * @param list<InvoiceMailLine> $lines
     */
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public Recipient $recipient,
        public string $recipientIban,
        public string $recipientAddress,
        public DateTimeInterface $invoiceDate,
        public CompoundPrice $total,
        public array $lines,
        public ?DateTimeInterface $sepaTransferDate,
    ) {}
}
