<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Helpers;

use Filament\Resources\RelationManagers\RelationManager;

final class IsOpen
{
    public static function checkOwner(RelationManager $livewire, mixed $record): bool
    {
        return !GetTransaction::get($livewire, $record)->isCompleted();
    }
}
