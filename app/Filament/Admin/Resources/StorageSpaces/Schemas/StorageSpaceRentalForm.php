<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Schemas;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\StorageSpaceRental;
use App\Rules\NoOverlappingStorageSpaceRental;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class StorageSpaceRentalForm
{
    public static function forStorageSpace(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('storage_space_id')
                ->default(static fn (RelationManager $livewire): mixed => $livewire->getOwnerRecord()->getKey()),
            Select::make('member_id')
                ->label(__('labels.member'))
                ->relationship('member', 'id')
                ->getOptionLabelFromRecordUsing(static fn (Member $record) => $record->name)
                ->searchable(['last_name', 'first_name', 'infix_name'])
                ->required()
                ->live(),
            ...self::datePickers(),
        ]);
    }

    public static function forMember(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('storage_space_location_id')
                ->label(__('labels.location'))
                ->options(
                    static fn (): array => StorageSpaceLocation::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray(),
                )
                ->required()
                ->live()
                ->dehydrated(false)
                ->formatStateUsing(static fn (?StorageSpaceRental $record): ?string => $record?->storageSpace?->location?->name)
                ->afterStateUpdated(static fn (Set $set) => $set('storage_space_id', null)),
            Select::make('storage_space_id')
                ->label(__('labels.space_number'))
                ->options(
                    static fn (Get $get): array => StorageSpace::query()
                        ->where('storage_space_location_id', $get('storage_space_location_id'))
                        ->pluck('number', 'id')
                        ->toArray(),
                )
                ->searchable()
                ->getSearchResultsUsing(
                    static fn (string $search, Get $get): array => StorageSpace::query()
                        ->where('storage_space_location_id', $get('storage_space_location_id'))
                        ->where('number', 'like', "%{$search}%")
                        ->pluck('number', 'id')
                        ->toArray(),
                )
                ->required()
                ->live(),
            Hidden::make('member_id')
                ->default(static fn (RelationManager $livewire): mixed => $livewire->getOwnerRecord()->getKey()),
            ...self::datePickers(),
        ]);
    }

    /**
     * @return array<Field>
     */
    private static function datePickers(): array
    {
        return [
            DatePicker::make('start_date')
                ->label(__('labels.start_date'))
                ->native(false)
                ->default(now())
                ->required()
                ->live()
                ->rules(
                    static function (Get $get, ?Model $record): array {
                        $storageSpaceId = $get('storage_space_id');
                        $memberId = $get('member_id');

                        if ($storageSpaceId === null || $memberId === null) {
                            return [];
                        }

                        $endDate = $get('end_date') !== null ? CarbonImmutable::create($get('end_date')) : null;

                        return [
                            new NoOverlappingStorageSpaceRental(
                                storageSpaceId: (int) $storageSpaceId,
                                startDate: CarbonImmutable::create($get('start_date')),
                                endDate: $endDate,
                                excludeRentalIds: $record instanceof StorageSpaceRental ? [$record->id] : [],
                            ),
                        ];
                    },
                ),
            DatePicker::make('end_date')
                ->label(__('labels.end_date'))
                ->native(false)
                ->afterOrEqual('start_date'),
        ];
    }
}
