<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SepaBatchId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class SepaBatchIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return SepaBatchId::class;
    }
}
