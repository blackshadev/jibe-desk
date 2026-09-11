<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Pages;

use App\Filament\Admin\Actions\ImportMt940Action;
use App\Filament\Admin\Resources\BankStatements\BankStatementResource;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListBankStatements extends ListRecords
{
    #[Override]
    protected static string $resource = BankStatementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ImportMt940Action::make(),
        ];
    }
}
