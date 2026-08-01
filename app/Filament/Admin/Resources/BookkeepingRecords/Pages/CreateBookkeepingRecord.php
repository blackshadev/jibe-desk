<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Pages;

use App\Filament\Admin\Resources\BookkeepingRecords\BookkeepingRecordResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateBookkeepingRecord extends CreateRecord
{
    #[Override]
    protected static string $resource = BookkeepingRecordResource::class;
}
