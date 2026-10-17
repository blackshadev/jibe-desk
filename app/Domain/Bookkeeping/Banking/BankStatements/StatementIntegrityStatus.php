<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\Banking\BankStatements;

enum StatementIntegrityStatus: string
{
    case Valid = 'valid';
    case Mismatch = 'mismatch';
}
