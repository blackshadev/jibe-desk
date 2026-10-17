<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class PurchaseOrderIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return PurchaseOrderId::class;
    }
}
