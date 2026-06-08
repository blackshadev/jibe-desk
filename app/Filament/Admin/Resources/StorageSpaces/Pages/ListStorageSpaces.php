<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;

final class ListStorageSpaces extends ListRecords
{
    protected static string $resource = StorageSpaceResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'available' => Tabs\Tab::make(__('labels.available'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->available()
                ),
            'unavailable' => Tabs\Tab::make(__('labels.unavailable'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->unavailable()
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GenerateStorageSpacesAction::make(),
        ];
    }
}
