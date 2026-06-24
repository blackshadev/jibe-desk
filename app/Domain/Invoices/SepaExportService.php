<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaExportService
{
    public function export(InvoiceBatchId $batchId): SepaExport;
}
