<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\MandateId;
use App\Domain\Invoices\PaymentInformationId;
use App\Domain\Members\MemberId;
use Tests\UnitTestCase;

final class MandateIdTest extends UnitTestCase
{
    public function test_it_generates_formatted_value(): void
    {
        $memberId = MemberId::create(42);
        $paymentInformationId = PaymentInformationId::create(7);

        $subject = new MandateId($memberId, $paymentInformationId);

        static::assertSame('C000042-000007', $subject->value);
    }

    public function test_it_pads_values_with_zeros(): void
    {
        $subject = new MandateId(MemberId::create(1), PaymentInformationId::create(1));

        static::assertSame('C000001-000001', $subject->value);
    }
}
