<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditStorageSpace extends EditRecord
{
    protected static string $resource = StorageSpaceResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return __('labels.storage_space');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
