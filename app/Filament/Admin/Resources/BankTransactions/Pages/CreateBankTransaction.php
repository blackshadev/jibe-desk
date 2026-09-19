<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Pages;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use App\Models\BankTransaction;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateBankTransaction extends CreateRecord
{
    #[Override]
    protected static string $resource = BankTransactionResource::class;

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
        /** @var BankTransaction $record */
        $record = parent::handleRecordCreation($data);

        $bankTransactionId = BankTransactionId::create($record->id);
        $service = app(BankTransactionService::class);
        $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));

        return $record;
    }
}
