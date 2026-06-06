<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStorageSpace extends CreateRecord
{
    protected static string $resource = StorageSpaceResource::class;
}
