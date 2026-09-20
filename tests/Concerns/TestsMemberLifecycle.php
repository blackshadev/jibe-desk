<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Members\Gender;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\InvoicesRelationManager;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MembershipSeeder;
use Livewire\Livewire;

trait TestsMemberLifecycle
{
    protected function seedBillingFixtures(): void
    {
        $this->seed(CostCenterSeeder::class);
        $this->seed(ActivitySeeder::class);
        $this->seed(MembershipSeeder::class);
    }

    protected function travelToDate(string $date): CarbonImmutable
    {
        $now = CarbonImmutable::parse($date);

        CarbonImmutable::setTestNow($now);

        return $now;
    }

    protected function resetTime(): void
    {
        CarbonImmutable::setTestNow();
    }

    protected function defaultMembership(): Membership
    {
        return Membership::query()->where('is_default', true)->firstOrFail();
    }

    protected function activityNamed(string $name): Activity
    {
        return Activity::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Register a new member through the public five-step registration wizard.
     *
     * @param array<string, mixed> $personalInfo overrides for the personal-information step
     * @param array<string, bool>  $activities   selections for the membership step
     */
    protected function registerMember(array $personalInfo = [], array $activities = ['windsurfing_lessons' => true]): Member
    {
        $email = $personalInfo['email'] ?? fake()->unique()->safeEmail();

        $personalData = [
            'first_name' => 'Jan',
            'last_name' => 'Vries',
            'email' => $email,
            'gender' => Gender::Male->value,
            'birthdate' => '1990-01-15',
            'address_street' => 'Surfstrand',
            'address_housenumber' => '2',
            'address_postalcode' => '1324CT',
            'address_city' => 'Almere',
            ...$personalInfo,
        ];

        $paymentData = [
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'banking_account_holder_name' => 'J. de Vries',
            'mandate_accepted' => '1',
        ];

        $this->post(route('register.welcome'))
            ->assertRedirect(route('register.membership'));

        $this->post(route('register.membership'), $activities)
            ->assertRedirect(route('register.personal-information'));

        $this->post(route('register.personal-information'), $personalData)
            ->assertRedirect(route('register.payment-information'));

        $this->post(route('register.payment-information'), $paymentData)
            ->assertRedirect(route('register.confirmation'));

        $this->post(route('register.confirmation'), [
            'confirm_data_correct' => '1',
            'confirm_membership' => '1',
        ])->assertRedirect(route('register.success'));

        return Member::query()->where('email', $email)->firstOrFail();
    }

    protected function joinActivity(Member $member, Activity $activity): void
    {
        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditMember::class,
        ])
            ->callTableAction('attach', data: ['recordId' => $activity->id])
            ->assertHasNoTableActionErrors();
    }

    /**
     * @param array<string, mixed> $changes
     */
    protected function updateMember(Member $member, array $changes): Member
    {
        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm($changes)
            ->call('save')
            ->assertHasNoFormErrors();

        return $member->refresh();
    }

    protected function generateInvoiceFor(Member $member): void
    {
        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditMember::class,
        ])->callTableAction('generate');
    }

    protected function invoiceFor(Member $member, string $date): Invoice
    {
        $month = CarbonImmutable::parse($date)->startOfMonth();

        return Invoice::query()
            ->where('member_id', $member->id)
            ->whereBetween('date', [$month, $month->copy()->endOfMonth()])
            ->firstOrFail();
    }

    protected function assertInvoiceLine(Invoice $invoice, string $description, float $price, float $quantity): void
    {
        $invoiceLine = $invoice->lines()->where('description', $description)->first();

        static::assertNotNull($invoiceLine, "Expected invoice line for '{$description}'");
        static::assertEqualsWithDelta($price, (float) $invoiceLine->price, 0.001);
        static::assertEqualsWithDelta($quantity, (float) $invoiceLine->quantity, 0.001);
    }
}
