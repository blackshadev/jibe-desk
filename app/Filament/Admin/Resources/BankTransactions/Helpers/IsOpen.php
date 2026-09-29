<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Helpers;

use App\Models\BankTransaction;
use Filament\Resources\RelationManagers\RelationManager;

final class IsOpen
{
    public static function checkOwner(RelationManager $livewire, mixed $record): bool
    {
        return !GetTransaction::get($livewire, $record)->isCompleted();
    }

    public static function checkInverse(RelationManager $livewire, mixed $record): bool
    {
        $ownerRecord = $livewire->getOwnerRecord();
        if ($ownerRecord instanceof BankTransaction) {
            return !$ownerRecord->isCompleted();
        }
        if ($record instanceof BankTransaction) {
            return !$record->isCompleted();
        }

        return true;
    }
}
