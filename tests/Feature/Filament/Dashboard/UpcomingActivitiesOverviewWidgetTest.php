<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\UpcomingActivitiesOverviewWidget;
use App\Models\Activity;
use App\Models\Member;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class UpcomingActivitiesOverviewWidgetTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_it_counts_active_activities_and_participants(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        $first = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $second = Activity::factory()->createQuietly(['end_date' => now()->addMonths(2)]);
        Activity::factory()->createQuietly(['end_date' => now()->subDay()]);

        $first
            ->members()
            ->attach(
                Member::factory()->count(2)->createQuietly()->modelKeys(),
            );
        $second
            ->members()
            ->attach(
                Member::factory()->createQuietly()->getKey(),
            );

        Livewire::test(UpcomingActivitiesOverviewWidget::class)
            ->assertSee('2')
            ->assertSee(trans_choice('labels.participant_count', 3, ['count' => 3]));
    }

    public function test_it_is_only_visible_for_activity_administration(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);
        static::assertTrue(UpcomingActivitiesOverviewWidget::canView());

        $this->withUserHavingRole(RoleName::MemberAdministration);
        static::assertFalse(UpcomingActivitiesOverviewWidget::canView());
    }
}
