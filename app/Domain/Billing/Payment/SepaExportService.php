<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaExportService
{
    public function export(SepaBatchId $batchId): SepaExport;
}
