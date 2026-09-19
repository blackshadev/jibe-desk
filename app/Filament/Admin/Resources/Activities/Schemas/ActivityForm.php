<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Filament\Admin\Sections\BillableItemSection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->required(),
                TextInput::make('description'),
                DatePicker::make('start_date')
                    ->native(false)
                    ->required(),
                DatePicker::make('end_date')
                    ->native(false),
            ]),
            BillableItemSection::make(),
        ]);
    }
}
