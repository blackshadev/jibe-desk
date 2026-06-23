<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Pages;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class ViewBookkeepingRecord extends EditRecord
{
    protected static string $resource = BookkeepingRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
