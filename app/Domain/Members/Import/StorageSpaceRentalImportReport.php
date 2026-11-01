<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final readonly class StorageSpaceRentalImportReport
{
    public function __construct(
        public int $importedRentals,
    ) {}
}
