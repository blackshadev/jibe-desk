<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts\Pages;

use App\Filament\Admin\Resources\BankAccounts\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateBankAccount extends CreateRecord
{
    #[Override]
    protected static string $resource = BankAccountResource::class;
}
