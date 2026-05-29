<?php

declare(strict_types=1);

namespace App\Domain\Activities;

use App\Domain\Invoices\Billing\BillableItemId;

final readonly class Activity
{
    public function __construct(
        public ActivityId $id,
        public BillableItemId $billableItemId,
        public \DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
    ) {
    }
}
