<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Pages;

use App\Filament\Admin\Resources\Memberships\MembershipResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateMembership extends CreateRecord
{
    #[Override]
    protected static string $resource = MembershipResource::class;
}
