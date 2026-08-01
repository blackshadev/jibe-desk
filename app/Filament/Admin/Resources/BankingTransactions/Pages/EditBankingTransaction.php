<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditBankingTransaction extends EditRecord
{
    #[Override]
    protected static string $resource = BankingTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
