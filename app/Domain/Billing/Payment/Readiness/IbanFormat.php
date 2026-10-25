<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use Intervention\Validation\Rules\Iban;

final readonly class IbanFormat
{
    public static function isWellFormed(?string $iban): bool
    {
        if ($iban === null) {
            return false;
        }

        return new Iban()->isValid($iban);
    }
}
