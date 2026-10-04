<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\MembershipSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Override;
use Tests\FeatureTestCase;

final class MemberPortalScopingTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
    }

    public function test_member_sees_only_own_and_household_members(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $own = $user->member;
        $own->update(['household_id' => $household->id]);

        $householdMember = Member::factory()->inHousehold($household)->createQuietly();
        $other = Member::factory()->createQuietly();

        Livewire::test(ListMembers::class)
            ->assertSee([$own->name, $householdMember->name])
            ->assertDontSee($other->name);
    }

    public function test_member_cannot_view_admin_only_resources(): void
    {
        $this->actingAs(User::factory()->memberUser()->createQuietly());

        Livewire::test(ListInvoices::class)->assertForbidden();
    }

    public function test_member_can_view_own_purchase_order_but_not_others_and_cannot_manage_it(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $own = PurchaseOrder::factory()->open()->forMember($user->member)->createQuietly();
        $other = PurchaseOrder::factory()->open()->createQuietly();

        static::assertTrue(Gate::allows('view', $own));
        static::assertFalse(Gate::allows('view', $other));
        static::assertFalse(Gate::allows('update', $own));
        static::assertFalse(Gate::allows('delete', $own));
    }

    public function test_member_cannot_view_any_purchase_orders(): void
    {
        $this->actingAs(User::factory()->memberUser()->createQuietly());

        Livewire::test(ListPurchaseOrders::class)->assertForbidden();
    }

    public function test_member_can_view_own_invoice_but_not_others(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $own = Invoice::factory()->forMember($user->member)->withLines(1)->createQuietly();
        $other = Invoice::factory()->forMember(Member::factory()->createQuietly())->withLines(1)->createQuietly();

        static::assertTrue(Gate::allows('view', $own));
        static::assertFalse(Gate::allows('view', $other));

        Livewire::test(ViewInvoice::class, ['record' => $own->getRouteKey()])
            ->assertSuccessful();

        Livewire::test(ViewInvoice::class, ['record' => $other->getRouteKey()])
            ->assertForbidden();
    }

    public function test_member_can_view_own_purchase_order_page_but_not_others(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $own = PurchaseOrder::factory()->open()->forMember($user->member)->createQuietly();
        $other = PurchaseOrder::factory()->open()->forMember(Member::factory()->createQuietly())->createQuietly();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $own->getRouteKey()])
            ->assertSuccessful();

        Livewire::test(ViewPurchaseOrder::class, ['record' => $other->getRouteKey()])
            ->assertForbidden();
    }

    public function test_member_does_not_see_financial_relation_managers_on_own_invoice(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $invoice = Invoice::factory()->forMember($user->member)->withLines(1)->createQuietly();

        $component = Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->instance();

        static::assertSame([], $component->getRelationManagers());
    }

    public function test_member_does_not_see_financial_relation_managers_on_own_purchase_order(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $purchaseOrder = PurchaseOrder::factory()->open()->forMember($user->member)->createQuietly();

        $component = Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])->instance();

        static::assertSame([], $component->getRelationManagers());
    }

    public function test_member_create_purchase_order_is_assigned_to_self(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'description' => 'Declaratie',
                'date' => '2026-10-02',
                'image_path' => UploadedFile::fake()->image('bon.jpg'),
                'lines' => [
                    ['description' => 'Materiaal', 'price' => 25, 'price_vat' => 5.25],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'member_id' => $user->member->id,
            'description' => 'Declaratie',
        ]);
    }
}
