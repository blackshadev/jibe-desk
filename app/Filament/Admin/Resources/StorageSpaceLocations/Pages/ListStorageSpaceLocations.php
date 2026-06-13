<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaceLocations\Pages;

use App\Filament\Admin\Resources\StorageSpaceLocations\StorageSpaceLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListStorageSpaceLocations extends ListRecords
{
    protected static string $resource = StorageSpaceLocationResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
