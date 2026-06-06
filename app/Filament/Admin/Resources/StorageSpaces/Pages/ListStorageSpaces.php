<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListStorageSpaces extends ListRecords
{
    protected static string $resource = StorageSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GenerateStorageSpacesAction::make(),
        ];
    }
}
