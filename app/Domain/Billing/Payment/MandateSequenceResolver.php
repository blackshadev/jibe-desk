<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeInterface;

final readonly class MandateSequenceResolver
{
    public function resolve(
        ?DateTimeInterface $lastCollectedAt,
        ?SequenceType $frozenSequence = null,
    ): SequenceType {
        if ($frozenSequence !== null) {
            return $frozenSequence;
        }

        return $lastCollectedAt === null ? SequenceType::First : SequenceType::Recurring;
    }
}
