<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Admin\Resources\Members\Pages\ViewMember;
use App\Filament\Admin\Resources\Members\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\MemberObjectsRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\StorageSpaceRentalsRelationManager;
use App\Models\Member;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MemberPendingRelationManagersTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    /** @return list<class-string> */
    private static function relationManagers(): array
    {
        return [
            InvoicesRelationManager::class,
            ActivitiesRelationManager::class,
            PurchaseOrdersRelationManager::class,
            MemberObjectsRelationManager::class,
            StorageSpaceRentalsRelationManager::class,
        ];
    }

    public function test_relation_managers_are_read_only_for_pending_members(): void
    {
        $this->withAuthorizedUser();

        $pending = Member::factory()->pending()->createQuietly();

        foreach (self::relationManagers() as $manager) {
            $instance = Livewire::test($manager, [
                'ownerRecord' => $pending,
                'pageClass' => ViewMember::class,
            ])->instance();

            static::assertTrue($instance->isReadOnly(), $manager);
        }
    }

    public function test_relation_managers_are_writable_for_accepted_members(): void
    {
        $this->withAuthorizedUser();

        $accepted = Member::factory()->createQuietly();

        foreach (self::relationManagers() as $manager) {
            $instance = Livewire::test($manager, [
                'ownerRecord' => $accepted,
                'pageClass' => ViewMember::class,
            ])->instance();

            static::assertFalse($instance->isReadOnly(), $manager);
        }
    }
}
