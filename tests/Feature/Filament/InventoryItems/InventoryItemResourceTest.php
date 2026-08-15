<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InventoryItems;

use App\Filament\Admin\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InventoryItemResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_inventory_items(): void
    {
        $this->withAuthorizedUser();

        InventoryItem::factory()->createOne();
        InventoryItem::factory()->createOne();

        Livewire::test(ListInventoryItems::class)
            ->assertCanSeeTableRecords(InventoryItem::all());
    }

    public function test_can_create_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne();

        Livewire::test(CreateInventoryItem::class)
            ->fillForm([
                'inventory_category_id' => $category->id,
                'description' => 'Nieuwe zeil',
                'date' => '2026-06-15',
                'amount' => 499.99,
                'write_off_period_years' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_items', [
            'inventory_category_id' => $category->id,
            'description' => 'Nieuwe zeil',
            'amount' => 499.99,
            'write_off_period_years' => 5,
        ]);
    }

    public function test_can_edit_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $item = InventoryItem::factory()->createOne(['amount' => 100]);

        Livewire::test(EditInventoryItem::class, ['record' => $item->id])
            ->fillForm([
                'amount' => 200,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'amount' => 200,
        ]);
    }

    public function test_can_delete_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $item = InventoryItem::factory()->createOne();

        Livewire::test(EditInventoryItem::class, ['record' => $item->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('inventory_items', [
            'id' => $item->id,
        ]);
    }
}
