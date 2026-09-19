<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Helpers;

use App\Models\BankTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use UnexpectedValueException;

final class GetTransaction
{
    public static function get(RelationManager $livewire, mixed $record): BankTransaction
    {
        $resource = $livewire->getOwnerRecord();
        if ($resource instanceof BankTransaction) {
            return $resource;
        }
        if ($record instanceof BankTransaction) {
            return $record;
        }

        throw new UnexpectedValueException('Unexpected record type');
    }
}
