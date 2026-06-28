<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\PaymentInformationId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class PaymentInformationIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return PaymentInformationId::class;
    }
}
