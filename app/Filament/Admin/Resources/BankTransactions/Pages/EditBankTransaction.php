<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Pages;

use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditBankTransaction extends EditRecord
{
    #[Override]
    protected static string $resource = BankTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
