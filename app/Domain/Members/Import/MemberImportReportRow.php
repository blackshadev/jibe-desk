<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final readonly class MemberImportReportRow
{
    public function __construct(
        public string $lidnr,
        public string $name,
        public string $email,
    ) {}
}
