<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Helpers;

use App\Models\BankingTransaction;
use Filament\Resources\RelationManagers\RelationManager;

final class IsOpen
{
    public static function checkResource(BankingTransaction $resource): bool
    {
        return !$resource->isCompleted();
    }

    public static function checkOwner(RelationManager $livewire): bool
    {
        /** @var BankingTransaction $resource */
        $resource = $livewire->getOwnerRecord();
        return !$resource->isCompleted();
    }
}
