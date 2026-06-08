<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaceLocations\Pages;

use App\Filament\Admin\Resources\StorageSpaceLocations\StorageSpaceLocationResource;
use App\Models\BillableItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateStorageSpaceLocation extends CreateRecord
{
    protected static string $resource = StorageSpaceLocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $item = BillableItem::createDefault([
            'description' => 'Opslagplek: ' . $data['name'],
        ]);

        return parent::handleRecordCreation([
            ...$data,
            'billable_item_id' => $item->id,
        ]);
    }
}
