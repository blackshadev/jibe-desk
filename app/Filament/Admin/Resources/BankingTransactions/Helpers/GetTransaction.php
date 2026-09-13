<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Helpers;

use App\Models\BankingTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use UnexpectedValueException;

final class GetTransaction
{
    public static function get(RelationManager $livewire, mixed $record): BankingTransaction
    {
        $resource = $livewire->getOwnerRecord();
        if ($resource instanceof BankingTransaction) {
            return $resource;
        }
        if ($record instanceof BankingTransaction) {
            return $record;
        }

        throw new UnexpectedValueException('Unexpected record type');
    }
}
