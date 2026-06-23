<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Pages;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBookkeepingRecord extends CreateRecord
{
    protected static string $resource = BookkeepingRecordResource::class;
}
