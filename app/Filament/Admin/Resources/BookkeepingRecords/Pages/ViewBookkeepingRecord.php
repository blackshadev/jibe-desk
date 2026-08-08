<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Pages;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;

final class ViewBookkeepingRecord extends ViewRecord
{
    #[Override]
    protected static string $resource = BookkeepingRecordResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.bookkeeping_record');
    }
}
