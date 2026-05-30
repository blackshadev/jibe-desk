<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes\Pages;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Filament\Admin\Resources\MemberObjectTypes\MemberObjectTypeResource;
use App\Models\BillableItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateMemberObjectType extends CreateRecord
{
    protected static string $resource = MemberObjectTypeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $item = BillableItem::createDefault([
            'bill_period' => BillPeriod::Once,
        ]);

        return parent::handleRecordCreation([
            ...$data,
            'billable_item_id' => $item->id,
        ]);
    }
}
