<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Billing\BillableItems\BillPeriod;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\RelationManagers\BillableItemInstancesRelationManager;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Member;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class BillableItemInstancesRelationManagerTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_refresh_reloads_the_billable_item_end_dates(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        $billable = BillableItem::factory()->create(['bill_period' => BillPeriod::Monthly]);
        $instance = BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $component = Livewire::test(BillableItemInstancesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditMember::class,
        ]);

        $instance->update(['end_date' => '2026-12-31 23:59:59']);

        $component
            ->call('refresh')
            ->assertTableColumnStateSet('end_date', Carbon::parse('2026-12-31'), $instance);
    }
}
