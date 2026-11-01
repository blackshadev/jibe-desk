<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final readonly class StorageSpaceRentalImportRequest
{
    public function __construct(
        public string $path,
        public bool $dryRun,
    ) {}
}
