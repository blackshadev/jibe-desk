<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Schemas;

use App\Filament\Admin\Sections\BillableItemSection;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

final class ExtraMembershipItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('code')
                            ->label(__('labels.code'))
                            ->disabledOn(Operation::Edit)
                            ->required(),
                    ]),
                BillableItemSection::make(),
            ]);
    }
}
