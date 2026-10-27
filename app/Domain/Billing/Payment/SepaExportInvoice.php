<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\SharedKernel\CompoundPrice;
use DateTimeInterface;

final readonly class SepaExportInvoice
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public CompoundPrice $total,
        public string $iban,
        public string $bic,
        public MandateId $mandateId,
        public PaymentInformationId $paymentInformationId,
        public DateTimeInterface $mandateDate,
        public ?DateTimeInterface $lastCollectedAt,
        public SequenceType $sequenceType,
    ) {}

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }

    public function isCredit(): bool
    {
        return $this->total->price < 0.0;
    }

    public function category(): SepaCategory
    {
        return $this->isCredit()
            ? SepaCategory::Credit
            : SepaCategory::forSequenceType($this->sequenceType);
    }
}
