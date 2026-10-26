<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum MandateExpirationStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
