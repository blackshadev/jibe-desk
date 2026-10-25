<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;

final readonly class MandateExpiry
{
    public const int MONTHS_WITHOUT_COLLECTION = 36;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function expiresAt(DateTimeInterface $signedAt, ?DateTimeInterface $lastCollectedAt): DateTimeInterface
    {
        return $this->addMonthsWithoutOverflow(
            $lastCollectedAt ?? $signedAt,
            self::MONTHS_WITHOUT_COLLECTION,
        );
    }

    public function state(
        DateTimeInterface $signedAt,
        ?DateTimeInterface $lastCollectedAt,
        ?DateTimeInterface $revokedAt,
    ): MandateState {
        if ($revokedAt !== null) {
            return MandateState::Revoked;
        }

        $expiresAt = $this->expiresAt($signedAt, $lastCollectedAt);

        if ($this->clock->now()->format('Y-m-d') >= $expiresAt->format('Y-m-d')) {
            return MandateState::Expired;
        }

        return MandateState::Active;
    }

    private function addMonthsWithoutOverflow(DateTimeInterface $date, int $months): DateTimeInterface
    {
        $immutable = DateTimeImmutable::createFromInterface($date);
        $firstOfTargetMonth = $immutable->setDate(
            (int) $immutable->format('Y'),
            (int) $immutable->format('n') + $months,
            1,
        );

        return $firstOfTargetMonth->setDate(
            (int) $firstOfTargetMonth->format('Y'),
            (int) $firstOfTargetMonth->format('n'),
            min((int) $immutable->format('j'), (int) $firstOfTargetMonth->format('t')),
        );
    }
}
