<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;

final class MemberTest extends FeatureTestCase
{
    private const NOW = '2026-06-15 12:00:00';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::NOW));
    }

    public function test_member_without_stop_date_is_active(): void
    {
        $member = Member::factory()->createQuietly(['stopped_at' => null]);

        static::assertTrue($member->isActive());
        static::assertFalse($member->isScheduledForStopping());
    }

    public function test_member_with_future_stop_date_is_active_and_scheduled(): void
    {
        $member = Member::factory()->createQuietly(['stopped_at' => now()->addMonth()]);

        static::assertTrue($member->isActive());
        static::assertTrue($member->isScheduledForStopping());
    }

    public function test_member_with_past_stop_date_is_inactive(): void
    {
        $member = Member::factory()->createQuietly(['stopped_at' => now()->subDay()]);

        static::assertFalse($member->isActive());
        static::assertFalse($member->isScheduledForStopping());
    }

    public function test_pending_member_is_never_active_or_scheduled(): void
    {
        $member = Member::factory()->pending()->scheduled()->createQuietly();

        static::assertFalse($member->isActive());
        static::assertFalse($member->isScheduledForStopping());
    }

    public function test_active_scope_includes_null_and_future_stop_dates(): void
    {
        $neverStopped = Member::factory()->createQuietly(['stopped_at' => null]);
        $scheduled = Member::factory()->scheduled()->createQuietly();
        $stopped = Member::factory()->createQuietly(['stopped_at' => now()->subDay()]);
        $pending = Member::factory()->pending()->createQuietly();

        $activeIds = Member::query()->active()->pluck('id')->all();

        static::assertContains($neverStopped->id, $activeIds);
        static::assertContains($scheduled->id, $activeIds);
        static::assertNotContains($stopped->id, $activeIds);
        static::assertNotContains($pending->id, $activeIds);
    }

    public function test_scheduled_scope_only_includes_accepted_members_with_a_future_stop_date(): void
    {
        $scheduled = Member::factory()->scheduled()->createQuietly();
        $neverStopped = Member::factory()->createQuietly(['stopped_at' => null]);
        $stopped = Member::factory()->createQuietly(['stopped_at' => now()->subDay()]);
        $pendingScheduled = Member::factory()->pending()->scheduled()->createQuietly();

        $scheduledIds = Member::query()->scheduled()->pluck('id')->all();

        static::assertSame([$scheduled->id], $scheduledIds);
        static::assertNotContains($neverStopped->id, $scheduledIds);
        static::assertNotContains($stopped->id, $scheduledIds);
        static::assertNotContains($pendingScheduled->id, $scheduledIds);
    }

    public function test_stopped_scope_only_includes_past_stop_dates(): void
    {
        $stopped = Member::factory()->createQuietly(['stopped_at' => now()->subDay()]);
        $scheduled = Member::factory()->scheduled()->createQuietly();
        $neverStopped = Member::factory()->createQuietly(['stopped_at' => null]);

        $stoppedIds = Member::query()->stopped()->pluck('id')->all();

        static::assertSame([$stopped->id], $stoppedIds);
        static::assertNotContains($scheduled->id, $stoppedIds);
        static::assertNotContains($neverStopped->id, $stoppedIds);
    }
}
