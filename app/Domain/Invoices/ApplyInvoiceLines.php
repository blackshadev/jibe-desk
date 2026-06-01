<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class ApplyInvoiceLines
{
    public function __construct(
        public DateTimeInterface $date,
        public MemberId $memberId,
        public BillableItemList $items,
    ) {
    }
}
