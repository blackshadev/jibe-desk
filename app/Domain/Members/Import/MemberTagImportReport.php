<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final readonly class MemberTagImportReport
{
    public function __construct(
        public int $importedTags,
    ) {}
}
