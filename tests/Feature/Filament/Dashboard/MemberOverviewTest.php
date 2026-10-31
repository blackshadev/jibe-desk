<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use App\Models\Member;
use Carbon\CarbonImmutable;
use ReflectionMethod;
use Tests\FeatureTestCase;

final class MemberOverviewTest extends FeatureTestCase
{
    public function test_headcount_chart_keeps_scheduled_members_and_drops_past_stops(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $createdAt = CarbonImmutable::parse('2025-01-01 08:00:00');

        Member::factory()->createQuietly(['created_at' => $createdAt, 'stopped_at' => null]);
        Member::factory()->createQuietly([
            'created_at' => $createdAt,
            'stopped_at' => CarbonImmutable::parse('2026-12-31 23:59:59'),
        ]);
        Member::factory()->createQuietly([
            'created_at' => $createdAt,
            'stopped_at' => CarbonImmutable::parse('2026-05-31 23:59:59'),
        ]);
        Member::factory()->createQuietly([
            'created_at' => CarbonImmutable::parse('2026-07-01 08:00:00'),
            'stopped_at' => null,
        ]);

        $chart = $this->headcountChart();

        static::assertSame(2, $chart[array_key_last($chart)]);
    }

    /** @return array<string, int> */
    private function headcountChart(): array
    {
        $method = new ReflectionMethod(MemberOverview::class, 'getMembersByCreatedByMonth');
        $method->setAccessible(true);

        $chart = $method->invoke(new MemberOverview());

        if (!is_array($chart)) {
            static::fail('Expected the headcount chart to be an array.');
        }

        /** @var array<string, int> $chart */
        return $chart;
    }
}
