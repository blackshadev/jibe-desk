<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['infix_name'])) {
            $data['infix_name'] = '';
        }

        return $data;
    }
}
