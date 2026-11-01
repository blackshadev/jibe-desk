<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface CostCenterImport
{
    /**
     * @throws CostCenterImportException when a file cannot be read, is malformed, or the files disagree
     */
    public function import(CostCenterImportRequest $request): CostCenterImportReport;
}
