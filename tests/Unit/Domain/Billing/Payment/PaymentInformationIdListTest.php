<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\PaymentInformationIdList;
use InvalidArgumentException;
use Tests\UnitTestCase;

final class PaymentInformationIdListTest extends UnitTestCase
{
    public function test_it_rejects_a_non_payment_information_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentInformationIdList([1]);
    }

    public function test_from_array_maps_values(): void
    {
        $subject = PaymentInformationIdList::fromArray([2, 5]);

        static::assertSame([2, 5], $subject->values());
    }
}
