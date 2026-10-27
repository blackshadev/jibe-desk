<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeInterface;

/**
 * Resolves the FRST/RCUR announcement for a collection.
 *
 * A frozen sequence is the plan recorded when the batch closed; it wins so a
 * re-export reproduces the presented file. Without a plan the mandate decides:
 * a mandate that was never collected is on its first collection.
 */
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
