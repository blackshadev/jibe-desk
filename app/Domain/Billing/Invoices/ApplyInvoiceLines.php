<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Billing\BillableItems\BillableItemList;
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class ApplyInvoiceLines
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $date,
        public BillableItemList $items,
    ) {}
}
