<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts\Pages;

use App\Filament\Admin\Resources\BankAccounts\BankAccountResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;

final class ViewBankAccount extends ViewRecord
{
    #[Override]
    protected static string $resource = BankAccountResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.bank_account');
    }
}
