<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

enum StatementChainStatus: string
{
    case Baseline = 'baseline';
    case Ok = 'ok';
    case Broken = 'broken';
}
