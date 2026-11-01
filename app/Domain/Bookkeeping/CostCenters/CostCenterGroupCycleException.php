<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters;

use RuntimeException;

final class CostCenterGroupCycleException extends RuntimeException
{
    public static function forGroup(string $number): self
    {
        return new self(sprintf('Cost group "%s" cannot become its own ancestor.', $number));
    }
}
