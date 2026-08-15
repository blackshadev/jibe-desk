<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InventoryCategories;

use App\Filament\Admin\Resources\InventoryCategories\Pages\CreateInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\EditInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\ListInventoryCategories;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InventoryCategoryResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_inventory_categories(): void
    {
        $this->withAuthorizedUser();

        InventoryCategory::factory()->createOne(['name' => 'Windsurf materiaal']);
        InventoryCategory::factory()->createOne(['name' => 'Boot materialen']);

        Livewire::test(ListInventoryCategories::class)
            ->assertCanSeeTableRecords(InventoryCategory::all());
    }

    public function test_can_create_inventory_category(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateInventoryCategory::class)
            ->fillForm([
                'name' => 'Apparaten',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_categories', [
            'name' => 'Apparaten',
        ]);
    }

    public function test_can_edit_inventory_category(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->fillForm([
                'name' => 'Elektronica',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_categories', [
            'id' => $category->id,
            'name' => 'Elektronica',
        ]);
    }

    public function test_can_delete_inventory_category(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('inventory_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_can_not_delete_inventory_category_with_items(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()
            ->has(InventoryItem::factory())
            ->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->assertActionHidden('delete');
    }
}
