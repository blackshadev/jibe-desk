<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Members\MemberId;

final readonly class MandateId
{
    public string $value;

    public function __construct(MemberId $memberId, PaymentInformationId $paymentInformationId)
    {
        $this->value = sprintf('C%06d-%06d', $memberId->value, $paymentInformationId->value);
    }
}
