<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Intervention\Validation\Rules\Bic;
use Intervention\Validation\Rules\Iban;

final class MandateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->required()
                    ->maxLength(34)
                    ->rule(new Iban())
                    ->readOnlyOn(Operation::Edit->value),
                TextInput::make('banking_bic')
                    ->label(__('labels.banking_bic'))
                    ->required()
                    ->maxLength(11)
                    ->rule(new Bic()),
                TextInput::make('banking_account_holder_name')
                    ->label(__('labels.banking_account_holder_name'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('mandate_accepted_date')
                    ->label(__('labels.mandate_date'))
                    ->required()
                    ->format('d-m-Y')
                    ->native(false)
                    ->maxDate(now())
                    ->readOnlyOn(Operation::Edit->value),
            ]);
    }
}
