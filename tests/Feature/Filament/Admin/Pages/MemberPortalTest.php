<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Pages;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use App\Filament\Admin\Widgets\Dashboard\MembershipCardWidget;
use App\Filament\Admin\Widgets\Dashboard\MyOpenInvoicesWidget;
use App\Filament\Admin\Widgets\Dashboard\MyStorageRentalsWidget;
use App\Filament\Admin\Widgets\Dashboard\MyUpcomingActivitiesWidget;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class MemberPortalTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_member_dashboard_shows_member_overview_and_household_members(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $widgets = new Dashboard()->getWidgets();

        $visibleWidgets = array_filter($widgets, static fn ($widget) => $widget::canView());

        static::assertSame(
            [
                MemberOverview::class,
                MembershipCardWidget::class,
                HouseholdMembersWidget::class,
                MyOpenInvoicesWidget::class,
                MyUpcomingActivitiesWidget::class,
                MyStorageRentalsWidget::class,
            ],
            $visibleWidgets,
        );
        static::assertTrue(MemberOverview::canView());
        static::assertTrue(HouseholdMembersWidget::canView());
    }

    public function test_admin_dashboard_hides_household_members_widget(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $widgets = new Dashboard()->getWidgets();

        $visibleWidgets = array_filter($widgets, static fn ($widget) => $widget::canView());

        static::assertSame([MemberOverview::class, MembershipCardWidget::class], $visibleWidgets);
        static::assertTrue(MemberOverview::canView());
        static::assertFalse(HouseholdMembersWidget::canView());
    }

    public function test_household_members_widget_shows_self_and_household_members(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $own = $user->member;
        $own->updateQuietly(['household_id' => $household->id]);

        $householdMember = Member::factory()->inHousehold($household)->createQuietly();
        $other = Member::factory()->createQuietly();

        Livewire::test(HouseholdMembersWidget::class)
            ->assertCanSeeTableRecords([$own, $householdMember])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_fortify_redirects_back_to_the_admin_panel(): void
    {
        static::assertSame('/dashboard', Fortify::redirects('password-reset'));
        static::assertSame('/dashboard', Fortify::redirects('login'));
    }
}
