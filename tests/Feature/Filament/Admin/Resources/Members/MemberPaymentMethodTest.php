<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Members\Gender;
use App\Domain\Members\PaymentMethod;
use App\Filament\Admin\Resources\Members\Pages\CreateMember;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Database\Seeders\MembershipSeeder;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MemberPaymentMethodTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_members_list_shows_the_payment_method_badge(): void
    {
        $this->withAuthorizedUser();

        $directDebit = Member::factory()->createQuietly();
        $transfer = Member::factory()->transfer()->createQuietly();

        Livewire::test(ListMembers::class)
            ->assertTableColumnFormattedStateSet('payment_method', 'Automatische incasso', $directDebit)
            ->assertTableColumnFormattedStateSet('payment_method', 'Handmatige betaling', $transfer);
    }

    public function test_payment_method_filter_returns_the_matching_members(): void
    {
        $this->withAuthorizedUser();

        $directDebit = Member::factory()->createQuietly();
        $transfer = Member::factory()->transfer()->createQuietly();

        Livewire::test(ListMembers::class)
            ->filterTable('payment_method', PaymentMethod::Transfer->value)
            ->assertCanSeeTableRecords([$transfer])
            ->assertCanNotSeeTableRecords([$directDebit]);
    }

    public function test_cleanup_filter_returns_direct_debit_members_without_an_active_mandate(): void
    {
        $this->withAuthorizedUser();

        $withoutMandate = Member::factory()->createQuietly();
        $withRevokedMandate = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['member_id' => $withRevokedMandate->id]);

        $withActiveMandate = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $withActiveMandate->id]);

        $transferWithoutMandate = Member::factory()->transfer()->createQuietly();

        Livewire::test(ListMembers::class)
            ->filterTable('cleanup_direct_debit_without_mandate')
            ->assertCanSeeTableRecords([$withoutMandate, $withRevokedMandate])
            ->assertCanNotSeeTableRecords([$withActiveMandate, $transferWithoutMandate]);
    }

    public function test_payment_tab_shows_the_payment_method_and_retained_iban_after_revocation(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
            ]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertFormSet(['payment_method' => PaymentMethod::DirectDebit->value])
            ->assertSee('NL91ABNA0417164300');

        static::assertSame(PaymentMethod::DirectDebit, $member->refresh()->payment_method);
    }

    public function test_an_admin_can_change_the_payment_method_to_transfer(): void
    {
        $this->withAuthorizedUser();

        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['payment_method' => PaymentMethod::Transfer->value])
            ->call('save')
            ->assertHasNoFormErrors();

        static::assertSame(PaymentMethod::Transfer, $member->refresh()->payment_method);
    }

    public function test_the_admin_create_form_defaults_to_direct_debit(): void
    {
        $this->withAuthorizedUser();
        $this->seed(MembershipSeeder::class);

        $membership = Membership::query()->where('is_default', true)->sole();

        Livewire::test(CreateMember::class)
            ->fillForm([
                'first_name' => 'Jan',
                'infix_name' => '',
                'last_name' => 'de Vries',
                'email' => 'jan@example.com',
                'gender' => Gender::Male->value,
                'birthdate' => '1990-01-15',
                'age' => 36,
                'membership_id' => $membership->id,
                'address_street' => 'Surfstrand',
                'address_housenumber' => '2',
                'address_postalcode' => '1324CT',
                'address_city' => 'Almere',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::query()->where('email', 'jan@example.com')->sole();

        static::assertSame(PaymentMethod::DirectDebit, $member->payment_method);
    }
}
