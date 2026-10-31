<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources\Members;

use App\Domain\Authorization\RoleName;
use App\Domain\Billing\BillableItems\BillPeriod;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Infrastructure\Mail\MailMailable;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Database\Seeders\MembershipSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Override;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class ScheduleStopActionTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    private const NOW = '2026-06-15 09:30:00';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
        $this->travelTo(CarbonImmutable::parse(self::NOW));
    }

    public function test_schedule_action_is_visible_only_for_active_unscheduled_members(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $active = Member::factory()->createQuietly(['stopped_at' => null]);
        $scheduled = Member::factory()->scheduled()->createQuietly();
        $pending = Member::factory()->pending()->createQuietly();

        $this->edit($active)->assertActionVisible('scheduleStop');
        $this->edit($scheduled)->assertActionHidden('scheduleStop');
        $this->edit($pending)->assertActionHidden('scheduleStop');
    }

    public function test_schedule_action_requires_the_schedule_permission(): void
    {
        $user = $this->withUserHavingRole(RoleName::FinancialAdministration);
        $user->givePermissionTo('update_members');

        $active = Member::factory()->createQuietly(['stopped_at' => null]);

        $this->edit($active)->assertActionHidden('scheduleStop');
    }

    public function test_scheduling_today_stops_the_member_immediately_and_emails_them(): void
    {
        Mail::fake();
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->createQuietly(['stopped_at' => null]);

        $this->edit($member)
            ->callAction('scheduleStop', data: ['stopped_at' => now()->format('d-m-Y')])
            ->assertHasNoActionErrors();

        $member->refresh();
        static::assertFalse($member->isActive());
        static::assertSame(self::NOW, $member->stopped_at?->format('Y-m-d H:i:s'));
        Mail::assertQueued(MailMailable::class);
    }

    public function test_scheduling_a_future_stop_keeps_the_member_active_until_the_end_of_that_day(): void
    {
        Mail::fake();
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->createQuietly(['stopped_at' => null]);

        $this->edit($member)
            ->callAction('scheduleStop', data: ['stopped_at' => '31-12-2026'])
            ->assertHasNoActionErrors();

        $member->refresh();
        static::assertTrue($member->isActive());
        static::assertTrue($member->isScheduled());
        static::assertSame('2026-12-31 23:59:59', $member->stopped_at?->format('Y-m-d H:i:s'));
        Mail::assertQueued(MailMailable::class);
    }

    public function test_cancelling_a_scheduled_stop_clears_the_date_without_emailing(): void
    {
        Mail::fake();
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->scheduled()->createQuietly();

        $this->edit($member)
            ->assertActionVisible('cancelStop')
            ->callAction('cancelStop')
            ->assertHasNoActionErrors();

        $member->refresh();
        static::assertNull($member->stopped_at);
        Mail::assertNotQueued(MailMailable::class);
    }

    public function test_cancel_action_is_hidden_for_active_and_immediately_stopped_members(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $active = Member::factory()->createQuietly(['stopped_at' => null]);
        $stopped = Member::factory()->createQuietly(['stopped_at' => now()->subDay()]);

        $this->edit($active)->assertActionHidden('cancelStop');
        $this->edit($stopped)->assertActionHidden('cancelStop');
    }

    public function test_scheduling_and_cancelling_reconciles_the_billable_item_instances(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        $member = Member::factory()->createQuietly(['stopped_at' => null]);
        $billable = BillableItem::factory()->create(['bill_period' => BillPeriod::Monthly]);
        $instance = BillableItemInstance::factory()->create([
            'member_id' => $member->id,
            'billable_item_id' => $billable->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->edit($member)
            ->callAction('scheduleStop', data: ['stopped_at' => '31-12-2026'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('billable_item_instances', [
            'id' => $instance->id,
            'end_date' => '2026-12-31 23:59:59',
        ]);

        $this->edit($member)
            ->callAction('cancelStop')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('billable_item_instances', [
            'id' => $instance->id,
            'end_date' => null,
        ]);
    }

    private function edit(Member $member): Testable
    {
        return Livewire::test(EditMember::class, ['record' => $member->getRouteKey()]);
    }
}
