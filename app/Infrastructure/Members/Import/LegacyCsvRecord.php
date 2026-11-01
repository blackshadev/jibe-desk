<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use DateTimeInterface;

/**
 * One validated CSV record of a legacy assignment export: a member, a numbered
 * object (tag or storage space) and its period.
 */
final readonly class LegacyCsvRecord
{
    public function __construct(
        public int $rowNumber,
        public string $lidnr,
        public string $number,
        public DateTimeInterface $startDate,
        public ?DateTimeInterface $endDate,
    ) {}

    public function isOpenEnded(): bool
    {
        return $this->endDate === null;
    }
}
