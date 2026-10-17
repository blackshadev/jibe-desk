<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\BillableItems;

use App\Domain\Billing\BillableItems\BillableItemId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class BillableItemIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return BillableItemId::class;
    }
}
