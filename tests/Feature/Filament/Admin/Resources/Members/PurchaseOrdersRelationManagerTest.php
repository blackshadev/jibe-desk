<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Filament\Admin\Resources\Members\Pages\ViewMember;
use App\Filament\Admin\Resources\Members\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Member;
use App\Models\PurchaseOrder;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class PurchaseOrdersRelationManagerTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_shows_only_purchase_orders_assigned_to_the_member(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly();
        $assigned = PurchaseOrder::factory()->forMember($member)->createQuietly();
        PurchaseOrder::factory()->createQuietly();

        Livewire::test(PurchaseOrdersRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords([$assigned]);
    }

    public function test_create_action_links_to_purchase_order_create_page_for_the_member(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly();

        Livewire::test(PurchaseOrdersRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertTableActionHasUrl(
                'create',
                PurchaseOrderResource::getUrl('create', ['member_id' => $member->id]),
            );
    }
}
