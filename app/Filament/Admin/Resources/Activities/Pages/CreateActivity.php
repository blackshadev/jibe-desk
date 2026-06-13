<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Pages;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Models\BillableItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateActivity extends CreateRecord
{
    protected static string $resource = ActivityResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $item = BillableItem::createDefault([
            'description' => 'Activiteit: ' . $data['name'],
        ]);

        return parent::handleRecordCreation([
            ...$data,
            'billable_item_id' => $item->id,
        ]);
    }
}
