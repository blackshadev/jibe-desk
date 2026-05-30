<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes\Pages;

use App\Filament\Admin\Resources\MemberObjectTypes\MemberObjectTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListMemberObjectTypes extends ListRecords
{
    protected static string $resource = MemberObjectTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
