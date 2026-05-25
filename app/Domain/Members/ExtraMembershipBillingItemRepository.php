<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ExtraMembershipBillingItemRepository
{
    public function getByCode(ExtraMembershipItemCode $code): BillableItemId;
}
