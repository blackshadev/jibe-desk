<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Pages;

use App\Filament\Admin\Resources\ExtraMembershipItems\ExtraMembershipItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditExtraMembershipItem extends EditRecord
{
    protected static string $resource = ExtraMembershipItemResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
