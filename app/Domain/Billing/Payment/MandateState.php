<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum MandateState: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
