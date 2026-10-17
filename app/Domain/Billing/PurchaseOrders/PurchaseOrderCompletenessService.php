<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderCompletenessService
{
    public function findProblems(PurchaseOrderIdList $ids): PurchaseOrderProblems;
}
