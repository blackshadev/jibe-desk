<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\SepaBatchId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ExportReadinessService
{
    public function assess(SepaBatchId $batchId): ExportReadinessReport;
}
