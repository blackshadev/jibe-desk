<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Pages;

use App\Filament\Admin\Resources\ExtraMembershipItems\ExtraMembershipItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExtraMembershipItems extends ListRecords
{
    protected static string $resource = ExtraMembershipItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
