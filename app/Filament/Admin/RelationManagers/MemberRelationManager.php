<?php

declare(strict_types=1);

namespace App\Filament\Admin\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Override;

abstract class MemberRelationManager extends RelationManager
{
    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (!$user instanceof User || !$user->isMember()) {
            return parent::canViewForRecord($ownerRecord, $pageClass);
        }

        return $user->can('view', $ownerRecord);
    }
}
