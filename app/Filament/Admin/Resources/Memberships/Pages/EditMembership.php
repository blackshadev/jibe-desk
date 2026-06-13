<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Pages;

use App\Filament\Admin\Resources\Memberships\MembershipResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditMembership extends EditRecord
{
    protected static string $resource = MembershipResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
