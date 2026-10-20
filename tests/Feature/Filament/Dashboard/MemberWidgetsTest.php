<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Filament\Admin\Widgets\Dashboard\MembershipCardWidget;
use App\Filament\Admin\Widgets\Dashboard\MyOpenInvoicesWidget;
use App\Filament\Admin\Widgets\Dashboard\MyStorageRentalsWidget;
use App\Filament\Admin\Widgets\Dashboard\MyUpcomingActivitiesWidget;
use App\Models\Activity;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\StorageSpaceRental;
use App\Models\User;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class MemberWidgetsTest extends FeatureTestCase
{
    public function test_member_sees_own_and_household_open_invoices_only(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $user->member->updateQuietly(['household_id' => $household->id]);

        $own = Invoice::factory()->forMember($user->member)->createQuietly(['status' => InvoiceStatus::Open]);
        $householdInvoice = Invoice::factory()
            ->forMember(Member::factory()->inHousehold($household)->createQuietly())
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $other = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);
        $paid = Invoice::factory()->forMember($user->member)->createQuietly(['status' => InvoiceStatus::Paid]);

        Livewire::test(MyOpenInvoicesWidget::class)
            ->assertCanSeeTableRecords([$own, $householdInvoice])
            ->assertCanNotSeeTableRecords([$other, $paid]);
    }

    public function test_member_sees_joined_upcoming_activities_only(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $joined = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $joined->members()->attach($user->member);

        $notJoined = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $ended = Activity::factory()->createQuietly(['end_date' => now()->subDay()]);
        $ended->members()->attach($user->member);

        Livewire::test(MyUpcomingActivitiesWidget::class)
            ->assertCanSeeTableRecords([$joined])
            ->assertCanNotSeeTableRecords([$notJoined, $ended]);
    }

    public function test_member_sees_only_current_storage_rentals(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $current = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->subMonth(),
            'end_date' => null,
        ]);
        $ended = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonth(),
        ]);
        $future = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->addMonth(),
            'end_date' => null,
        ]);

        Livewire::test(MyStorageRentalsWidget::class)
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$ended, $future]);
    }

    public function test_membership_card_renders_membership_household_and_volunteer_state(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $user->member->updateQuietly([
            'household_id' => $household->id,
            'is_volunteer' => true,
        ]);

        Livewire::test(MembershipCardWidget::class)
            ->assertSee($user->member->membership->name)
            ->assertSee($user->member->name)
            ->assertSee(__('labels.yes'));
    }

    public function test_staff_do_not_see_member_widgets(): void
    {
        $user = User::factory()->createQuietly();
        $this->actingAs($user);

        static::assertFalse(MyOpenInvoicesWidget::canView());
        static::assertFalse(MyUpcomingActivitiesWidget::canView());
        static::assertFalse(MyStorageRentalsWidget::canView());
    }
}
