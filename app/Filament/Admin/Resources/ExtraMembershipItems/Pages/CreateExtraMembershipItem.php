<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExtraMembershipItems\Pages;

use App\Filament\Admin\Resources\ExtraMembershipItems\ExtraMembershipItemResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateExtraMembershipItem extends CreateRecord
{
    protected static string $resource = ExtraMembershipItemResource::class;
}
