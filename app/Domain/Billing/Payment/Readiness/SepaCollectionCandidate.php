<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;

final readonly class SepaCollectionCandidate
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public ?MemberId $memberId,
        public CompoundPrice $total,
        public InvoiceStatus $status,
        public SequenceType $sequenceType,
    ) {}

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }

    public function isDebit(): bool
    {
        return $this->total->price > 0.0;
    }

    public function isRefund(): bool
    {
        return $this->total->price < 0.0;
    }
}
