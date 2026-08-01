<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Pages;

use App\Filament\Admin\Resources\ExtraMembershipItems\ExtraMembershipItemResource;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListExtraMembershipItems extends ListRecords
{
    #[Override]
    protected static string $resource = ExtraMembershipItemResource::class;
}
