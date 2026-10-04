<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Pages;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
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

    public function test_member_dashboard_shows_only_the_household_members_widget(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $widgets = new Dashboard()->getWidgets();

        static::assertSame([MemberOverview::class, HouseholdMembersWidget::class], $widgets);
    }

    public function test_admin_dashboard_shows_only_the_member_overview_widget(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $widgets = new Dashboard()->getWidgets();

        static::assertSame([MemberOverview::class], $widgets);
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
        static::assertSame('/admin', Fortify::redirects('password-reset'));
        static::assertSame('/admin', Fortify::redirects('login'));
    }
}
