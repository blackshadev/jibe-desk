<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberTagImport
{
    /**
     * @throws CsvImportException when the file cannot be read or a row is invalid
     */
    public function import(MemberTagImportRequest $request): MemberTagImportReport;
}
