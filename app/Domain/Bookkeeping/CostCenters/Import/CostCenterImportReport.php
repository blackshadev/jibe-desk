<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

final readonly class CostCenterImportReport
{
    /**
     * @param list<string> $costCentersAbsentFromFiles
     * @param list<string> $groupsAbsentFromFiles
     */
    public function __construct(
        public int $costCentersInFile,
        public int $groupsInFile,
        public int $costCentersCreated,
        public int $costCentersUpdated,
        public int $groupsCreated,
        public int $groupsUpdated,
        public array $costCentersAbsentFromFiles,
        public array $groupsAbsentFromFiles,
    ) {}
}
