<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Pages;

use App\Filament\Admin\Resources\Memberships\MembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListMemberships extends ListRecords
{
    protected static string $resource = MembershipResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
