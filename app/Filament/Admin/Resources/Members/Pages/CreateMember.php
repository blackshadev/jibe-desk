<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Domain\Members\PaymentMethod;
use App\Filament\Admin\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateMember extends CreateRecord
{
    #[Override]
    protected static string $resource = MemberResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['infix_name'] === null) {
            $data['infix_name'] = '';
        }

        $data['accepted_at'] = now();
        $data['payment_method'] = PaymentMethod::DirectDebit;

        return $data;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.member_created');
    }
}
