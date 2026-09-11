<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts\Schemas;

use App\Models\BankAccount;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.bank_account_information'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),
                        TextInput::make('iban')
                            ->label(__('labels.iban'))
                            ->required()
                            ->mutateDehydratedStateUsing(static fn (?string $state): ?string => $state === null ? null : BankAccount::normalizeIban($state))
                            ->rules([
                                static fn (Field $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                    $normalized = BankAccount::normalizeIban((string) $value);

                                    $query = BankAccount::query()->where('iban', $normalized);

                                    $record = $component->getRecord();
                                    if ($record instanceof BankAccount) {
                                        $query->where('id', '!=', $record->getKey());
                                    }

                                    if ($query->exists()) {
                                        $fail(__('validation.unique'));
                                    }
                                },
                            ]),
                        TextInput::make('bic')
                            ->label(__('labels.banking_bic'))
                            ->nullable(),
                        Toggle::make('active')
                            ->label(__('labels.active'))
                            ->default(true),
                    ]),
            ]);
    }
}
