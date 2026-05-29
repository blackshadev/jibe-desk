<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Pages;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return __('labels.activity');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
