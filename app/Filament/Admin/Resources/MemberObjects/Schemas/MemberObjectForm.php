<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjects\Schemas;

use App\Models\MemberObjectType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class MemberObjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('member_object_type_id')
                ->relationship(
                    'memberObjectType',
                    'name',
                    static fn (Builder $query) => $query->orderBy('id'),
                )
                ->required()
                ->live(),
            TextInput::make('name')
                ->label(static function (Get $get) {
                    $id = $get('member_object_type_id');
                    $object = $id !== null ? MemberObjectType::find($id) : null;

                    if ($object !== null) {
                        if ($object->name === 'Tag') {
                            return __('labels.object_tag_name');
                        }
                        if ($object->name === 'Sleutel') {
                            return __('labels.object_key_name');
                        }
                    }

                    return __('labels.description');
                })
                ->regex(static function (Get $get) {
                    $id = $get('member_object_type_id');
                    $object = $id !== null ? MemberObjectType::find($id) : null;

                    if ($object?->name === 'Tag') {
                        return '/^0\d{8}$/';
                    }
                })
                ->required(),
            DatePicker::make('start_date')
                ->label(__('labels.start_date'))
                ->native(false)
                ->date()
                ->default(now())
                ->required(),
            DatePicker::make('end_date')
                ->label(__('labels.end_date'))
                ->native(false)
                ->date(),
        ]);
    }
}
