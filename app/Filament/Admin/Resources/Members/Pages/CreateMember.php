<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['infix_name'] === null) {
            $data['infix_name'] = '';
        }

        return $data;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.member_created');
    }
}
