<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

enum MandateReadinessStatus: string
{
    case Active = 'active';
    case Missing = 'missing';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
