<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Members\MemberId;
use App\Domain\NumericId;

/** @template T of ?NumericId */
interface ApplyBillableItem
{
    /** @param T $billableItemId */
    public function __invoke(MemberId $memberId, ?NumericId $billableItemId): void;
}
