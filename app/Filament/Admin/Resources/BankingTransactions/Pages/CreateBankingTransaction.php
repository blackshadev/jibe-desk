<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Models\BankingTransaction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
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

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var BankingTransaction $record */
        $record = parent::handleRecordCreation($data);

        $bankTransactionId = BankTransactionId::create($record->id);
        $service = app(BankTransactionService::class);
        $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));

        return $record;
    }
}
