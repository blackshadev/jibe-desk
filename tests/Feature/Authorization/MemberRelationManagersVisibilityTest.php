<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\ViewMember;
use App\Filament\Admin\Resources\Members\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\BillableItemInstancesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\HouseholdMembersRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\MemberObjectsRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\OutgoingEmailsRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\StorageSpaceRentalsRelationManager;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\MembershipSeeder;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MemberRelationManagersVisibilityTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
    }

    /**
     * @return list<class-string>
     */
    private static function relationManagers(): array
    {
        return [
            HouseholdMembersRelationManager::class,
            InvoicesRelationManager::class,
            PurchaseOrdersRelationManager::class,
            BillableItemInstancesRelationManager::class,
            ActivitiesRelationManager::class,
            MemberObjectsRelationManager::class,
            StorageSpaceRentalsRelationManager::class,
            OutgoingEmailsRelationManager::class,
        ];
    }

    public function test_member_sees_every_relation_manager_for_visible_members(): void
    {
        $user = User::factory()->memberUser()->createQuietly();

        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $own = $user->member;
        $own->update(['household_id' => $household->id]);

        $householdMember = Member::factory()->inHousehold($household)->createQuietly();
        $other = Member::factory()->createQuietly();

        foreach (self::relationManagers() as $manager) {
            static::assertTrue($manager::canViewForRecord($own, ViewMember::class));
            static::assertTrue($manager::canViewForRecord($householdMember, ViewMember::class));
            static::assertFalse($manager::canViewForRecord($other, ViewMember::class));
        }
    }

    public function test_admin_roles_keep_per_resource_relation_manager_gating(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);
        $member = Member::factory()->createQuietly();

        static::assertTrue(HouseholdMembersRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertTrue(ActivitiesRelationManager::canViewForRecord($member, ViewMember::class));

        static::assertFalse(InvoicesRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertFalse(PurchaseOrdersRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertFalse(BillableItemInstancesRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertFalse(MemberObjectsRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertFalse(StorageSpaceRentalsRelationManager::canViewForRecord($member, ViewMember::class));
        static::assertFalse(OutgoingEmailsRelationManager::canViewForRecord($member, ViewMember::class));
    }
}
