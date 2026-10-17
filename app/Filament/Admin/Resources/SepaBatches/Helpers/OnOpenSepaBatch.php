<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Helpers;

use App\Domain\Invoices\SepaBatchStatus;
use App\Models\SepaBatch;

final class OnOpenSepaBatch
{
    public static function make(?SepaBatch $sepaBatch): bool
    {
        return $sepaBatch === null || $sepaBatch->status === SepaBatchStatus::Open;
    }
}
