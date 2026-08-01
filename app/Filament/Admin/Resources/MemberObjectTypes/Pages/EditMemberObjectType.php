<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes\Pages;

use App\Filament\Admin\Resources\MemberObjectTypes\MemberObjectTypeResource;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditMemberObjectType extends EditRecord
{
    #[Override]
    protected static string $resource = MemberObjectTypeResource::class;
}
