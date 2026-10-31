<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

enum ExportReadinessCategory: string
{
    case RefundExclusion = 'refund_exclusion';
    case Overridable = 'overridable';
    case BrokenData = 'broken_data';
    case BatchLevel = 'batch_level';
}
