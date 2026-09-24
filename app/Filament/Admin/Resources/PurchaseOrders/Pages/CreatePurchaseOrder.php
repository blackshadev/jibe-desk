<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Helpers\MemberCreditorPrefill;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreatePurchaseOrder extends CreateRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date'] ??= CarbonImmutable::now();
        $data['status'] = PurchaseOrderStatus::Open;

        return $data;
    }

    protected function afterFill(): void
    {
        $this->data['date'] = CarbonImmutable::now();
        $this->data['status'] = PurchaseOrderStatus::Open;

        $memberId = request()->query('member_id');
        if (!is_numeric($memberId)) {
            return;
        }

        $member = Member::find((int) $memberId);
        if ($member === null) {
            return;
        }

        $this->data['member_id'] = $member->id;

        foreach (MemberCreditorPrefill::for($member) as $field => $value) {
            $this->data[$field] = $value;
        }
    }
}
