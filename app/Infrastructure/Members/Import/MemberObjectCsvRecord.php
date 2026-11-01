<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use DateTimeInterface;

final readonly class MemberObjectCsvRecord
{
    public function __construct(
        public int $rowNumber,
        public string $memberNumber,
        public string $objectNumber,
        public DateTimeInterface $startDate,
        public ?DateTimeInterface $endDate,
    ) {}

    public function isOpenEnded(): bool
    {
        return $this->endDate === null;
    }
}
