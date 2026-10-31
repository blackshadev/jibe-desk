<?php

declare(strict_types=1);

namespace App\Filament\Admin\RelationManagers;

use App\Models\Member;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
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

    #[Override]
    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Member && $owner->isPending();
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
