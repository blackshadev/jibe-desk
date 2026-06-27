<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\ListInvoiceBatches;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\OutgoingEmails\Pages\ListOutgoingEmails;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class AuthorizationTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_member_administration_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_invoice_batches(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListInvoiceBatches::class)
            ->assertSuccessful();
    }

    public function test_activity_administration_can_view_activities(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListActivities::class)
            ->assertSuccessful();
    }

    public function test_technical_administration_can_view_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::TechnicalAdministration);

        Livewire::test(ListOutgoingEmails::class)
            ->assertSuccessful();
    }

    public function test_rental_administration_can_view_storage_spaces(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        Livewire::test(ListStorageSpaces::class)
            ->assertSuccessful();
    }

    public function test_activity_administration_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_member_administration_can_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }

    public function test_activity_administration_cannot_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListInvoices::class)
            ->assertForbidden();
    }

    public function test_rental_administration_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_member_administration_cannot_view_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListOutgoingEmails::class)
            ->assertForbidden();
    }

    public function test_user_without_role_cannot_access_resources(): void
    {
        $user = User::factory()->createQuietly();
        $this->actingAs($user);

        Livewire::test(ListMembers::class)
            ->assertForbidden();
    }

    public function test_invoicing_can_view_member_payment_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertTrue($user->can('view_member_payment_information'));
        static::assertTrue($user->can('update_member_payment_information'));
    }

    public function test_member_administration_cannot_view_member_payment_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertFalse($user->can('view_member_payment_information'));
    }

    public function test_member_administration_can_view_member_address_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertTrue($user->can('view_member_address_information'));
        static::assertTrue($user->can('update_member_address_information'));
    }

    public function test_invoicing_cannot_view_member_address_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertFalse($user->can('view_member_address_information'));
    }

    public function test_technical_administration_can_view_member_registration_data(): void
    {
        $user = $this->withUserHavingRole(RoleName::TechnicalAdministration);

        static::assertTrue($user->can('view_member_registration_data'));
        static::assertTrue($user->can('update_member_registration_data'));
    }

    public function test_member_administration_cannot_view_member_registration_data(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertFalse($user->can('view_member_registration_data'));
    }

    public function test_financial_administration_can_view_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_member_administration_cannot_view_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertForbidden();
    }

    public function test_activity_administration_cannot_view_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertForbidden();
    }
}
