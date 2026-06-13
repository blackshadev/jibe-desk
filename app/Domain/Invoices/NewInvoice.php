<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class NewInvoice
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public BillableItemList $items,
        public ?InvoiceBatchId $batchId = null,
    ) {}
}
