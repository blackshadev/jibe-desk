<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

enum SepaCategory
{
    case Credit;
    case DebitFirst;
    case DebitRecurring;

    public static function forSequenceType(SequenceType $sequenceType): self
    {
        return match ($sequenceType) {
            SequenceType::First => self::DebitFirst,
            SequenceType::Recurring => self::DebitRecurring,
        };
    }
}
