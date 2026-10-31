<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

final readonly class SpreadsheetImportRow
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public int $number,
        public array $values,
    ) {}
}
