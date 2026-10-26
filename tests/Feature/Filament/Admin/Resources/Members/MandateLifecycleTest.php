<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\Members\Pages\ViewMember;
use App\Filament\Admin\Resources\Members\RelationManagers\MandatesRelationManager;
use App\Infrastructure\Billing\Invoices\InvoiceMailRepositoryDb;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\SepaBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MandateLifecycleTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    #[Override]
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_financial_administration_can_create_a_mandate_for_a_member_without_payment_information(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('createMandate', data: [
                'banking_account_number' => 'NL91ABNA0417164300',
                'banking_bic' => 'ABNANL2A',
                'banking_account_holder_name' => 'Jan de Vries',
                'mandate_accepted_date' => '2026-01-15',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('payment_information', [
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'banking_account_holder_name' => 'Jan de Vries',
            'revoked_at' => null,
        ]);
    }

    public function test_creating_a_mandate_revokes_the_previous_active_mandate_and_uses_a_new_reference(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $old = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        $oldReference = $old->mandate_reference;

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('createMandate', data: [
                'banking_account_number' => 'NL02INGB0002447973',
                'banking_bic' => 'INGBNL2A',
                'banking_account_holder_name' => 'Jan de Vries',
                'mandate_accepted_date' => '2026-10-01',
            ])
            ->assertHasNoTableActionErrors();

        static::assertNotNull($old->refresh()->revoked_at);

        $new = $member->paymentInformation;

        static::assertNotNull($new);
        static::assertNotSame($oldReference, $new->mandate_reference);
        static::assertSame(1, PaymentInformation::query()->where('member_id', $member->id)->whereNull('revoked_at')->count());
    }

    public function test_create_mandate_requires_an_iban_bic_holder_and_signed_date(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('createMandate', data: [])
            ->assertHasTableActionErrors([
                'banking_account_number' => 'required',
                'banking_bic' => 'required',
                'banking_account_holder_name' => 'required',
                'mandate_accepted_date' => 'required',
            ]);
    }

    public function test_create_mandate_rejects_an_invalid_iban(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('createMandate', data: [
                'banking_account_number' => 'NOT-AN-IBAN',
                'banking_bic' => 'ABNANL2A',
                'banking_account_holder_name' => 'Jan de Vries',
                'mandate_accepted_date' => '2026-01-15',
            ])
            ->assertHasTableActionErrors(['banking_account_number']);
    }

    public function test_financial_administration_can_edit_the_active_mandate(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_bic' => 'ABNANL2A',
        ]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('editMandate', $mandate, data: [
                'banking_bic' => 'RABONL2U',
                'banking_account_holder_name' => 'J. de Vries',
            ])
            ->assertHasNoTableActionErrors();

        $mandate->refresh();

        static::assertSame('RABONL2U', $mandate->banking_bic);
        static::assertSame('J. de Vries', $mandate->banking_account_holder_name);
    }

    public function test_edit_form_shows_the_iban_and_signed_date_read_only(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->mountTableAction('editMandate', $mandate)
            ->assertFormFieldDisabled('banking_account_number')
            ->assertFormFieldDisabled('mandate_accepted_date')
            ->assertFormFieldEnabled('banking_bic')
            ->assertFormFieldEnabled('banking_account_holder_name');
    }

    public function test_edit_action_cannot_change_the_iban_or_signed_date(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'mandate_accepted_date' => '2025-01-15',
        ]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('editMandate', $mandate, data: [
                'banking_account_number' => 'NL20RABO0123456789',
                'banking_bic' => 'RABONL2U',
                'banking_account_holder_name' => 'J. de Vries',
                'mandate_accepted_date' => '2020-01-01',
            ]);

        $mandate->refresh();

        static::assertSame('NL91ABNA0417164300', $mandate->banking_account_number);
        static::assertSame('2025-01-15', $mandate->mandate_accepted_date->format('Y-m-d'));
    }

    public function test_edit_action_is_hidden_for_a_revoked_mandate(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $revoked = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['member_id' => $member->id]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertTableActionHidden('editMandate', $revoked)
            ->assertTableActionHidden('revokeMandate', $revoked);
    }

    public function test_financial_administration_can_revoke_the_active_mandate(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        $mandate = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('revokeMandate', $mandate);

        $mandate->refresh();

        static::assertNotNull($mandate->revoked_at);
        static::assertSame('NL91ABNA0417164300', $mandate->banking_account_number);
        static::assertNull($member->refresh()->paymentInformation);
    }

    public function test_revoke_keeps_the_iban_and_the_member_becomes_a_manual_payer(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $batch = SepaBatch::factory()->createQuietly([
            'invoice_date' => '2026-06-30',
            'sepa_transfer_date' => '2026-07-15',
        ]);
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(MandatesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callTableAction('revokeMandate', $member->paymentInformation);

        $mailData = app(InvoiceMailRepositoryDb::class)
            ->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNull($mailData->sepaTransferDate);
        static::assertSame('NL91ABNA0417164300', $member->paymentInformations()->first()?->banking_account_number);
    }

    public function test_member_administration_cannot_view_the_mandate_relation_manager(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);
        $member = Member::factory()->createQuietly();

        static::assertFalse(MandatesRelationManager::canViewForRecord($member, ViewMember::class));
    }

    public function test_member_user_cannot_view_the_mandate_relation_manager(): void
    {
        $user = User::factory()->createQuietly();
        $member = Member::factory()->createQuietly(['user_id' => $user->id]);
        $this->actingAs($user);

        static::assertFalse(MandatesRelationManager::canViewForRecord($member, ViewMember::class));
    }

    public function test_members_list_shows_the_mandate_state(): void
    {
        $this->withAuthorizedUser();
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-10-22'))
            ->createQuietly(['member_id' => $member->id]);

        Livewire::test(ListMembers::class)
            ->assertTableColumnStateSet('mandate_state', MandateExpirationStatus::Expired, $member);
    }

    public function test_payment_tab_shows_the_active_mandate_reference_and_state(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly(['id' => 42]);
        PaymentInformation::factory()->createQuietly([
            'id' => 7,
            'member_id' => $member->id,
        ]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('C000042-000007')
            ->assertSee('Actief');
    }

    public function test_payment_tab_no_longer_renders_the_uuid_reference(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertFormFieldDoesNotExist('uuid');
    }

    public function test_payment_tab_shows_no_active_mandate_after_revocation(): void
    {
        $this->withAuthorizedUser();
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['member_id' => $member->id]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('Geen actieve machtiging');
    }
}
