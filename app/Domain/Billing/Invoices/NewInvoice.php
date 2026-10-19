<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Billing\BillableItems\BillableItemList;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class NewInvoice
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public BillableItemList $items,
        public ?SepaBatchId $batchId = null,
    ) {}
}
