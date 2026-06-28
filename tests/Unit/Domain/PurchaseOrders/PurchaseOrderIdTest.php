<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
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
