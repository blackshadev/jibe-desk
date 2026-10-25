<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;

final readonly class ExportReadinessIssue
{
    public function __construct(
        public ExportReadinessReason $reason,
        public ?InvoiceId $invoiceId = null,
        public ?string $invoiceNumber = null,
        public ?MemberId $memberId = null,
        public ?string $memberName = null,
        public ?CompoundPrice $amount = null,
        public ?string $detail = null,
    ) {}

    public static function forInvoice(
        ExportReadinessReason $reason,
        SepaCollectionCandidate $candidate,
        ?string $detail = null,
    ): self {
        return new self(
            reason: $reason,
            invoiceId: $candidate->invoiceId,
            invoiceNumber: $candidate->invoiceNumber,
            memberId: $candidate->memberId,
            memberName: $candidate->recipientName,
            amount: $candidate->total,
            detail: $detail,
        );
    }

    public static function forBatch(ExportReadinessReason $reason, string $detail): self
    {
        return new self(reason: $reason, detail: $detail);
    }

    public function category(): ExportReadinessCategory
    {
        return $this->reason->category();
    }
}
