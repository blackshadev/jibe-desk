<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaceLocations\Pages;

use App\Filament\Admin\Resources\StorageSpaceLocations\StorageSpaceLocationResource;
use App\Models\StorageSpaceLocation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditStorageSpaceLocation extends EditRecord
{
    protected static string $resource = StorageSpaceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(static fn (StorageSpaceLocation $record): bool => $record->storageSpaces()->count() === 0),
        ];
    }
}
