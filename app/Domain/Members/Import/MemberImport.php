<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberImport
{
    /**
     * @throws MemberImportException when the import cannot run or a row is invalid
     */
    public function import(MemberImportRequest $request): MemberImportReport;
}
