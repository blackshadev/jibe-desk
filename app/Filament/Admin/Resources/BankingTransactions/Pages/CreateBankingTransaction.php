<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateBankingTransaction extends CreateRecord
{
    protected static string $resource = BankingTransactionResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['import_hash'] ??= hash('sha256', implode('|', [
            $data['date'] ?? '',
            $data['amount'] ?? '',
            $data['description'] ?? '',
            $data['banking_account_number'] ?? '',
        ]));

        return $data;
    }
}
