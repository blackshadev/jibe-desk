<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Pages;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditBookkeepingRecord extends EditRecord
{
    #[Override]
    protected static string $resource = BookkeepingRecordResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
