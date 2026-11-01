<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters;

final readonly class CostCenterGroupSubtree
{
    /**
     * @param list<int> $groupIds
     */
    public function __construct(
        private array $groupIds,
    ) {}

    public function contains(int $groupId): bool
    {
        return in_array($groupId, $this->groupIds, true);
    }
}
