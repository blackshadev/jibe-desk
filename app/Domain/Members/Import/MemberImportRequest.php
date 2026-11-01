<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use DateTimeInterface;

final readonly class MemberImportRequest
{
    public function __construct(
        public string $path,
        public bool $dryRun,
        public bool $validateOnly,
        public DateTimeInterface $billingStart,
        public DateTimeInterface $lastCollected,
    ) {}
}
