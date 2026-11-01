<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface StorageSpaceRentalImport
{
    /**
     * @throws CsvImportException when the file cannot be read or a row is invalid
     */
    public function import(StorageSpaceRentalImportRequest $request): StorageSpaceRentalImportReport;
}
