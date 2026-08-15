<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\InventoryItem;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;

final class InventoryItemTest extends FeatureTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-15 12:00:00');
    }

    #[Override]
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_zero_when_fully_written_off(): void
    {
        $item = InventoryItem::factory()->createOne([
            'date' => CarbonImmutable::now()->subYears(5),
            'amount' => 1000.00,
            'write_off_period_years' => 5,
        ]);

        static::assertSame(0.0, $item->residual_value);
    }

    public function test_it_returns_full_amount_when_not_written_off(): void
    {
        $item = InventoryItem::factory()->createOne([
            'date' => CarbonImmutable::now(),
            'amount' => 1000.00,
            'write_off_period_years' => 5,
        ]);

        static::assertSame(1000.00, $item->residual_value);
    }

    public function test_it_returns_partial_value_when_partially_written_off(): void
    {
        $item = InventoryItem::factory()->createOne([
            'date' => CarbonImmutable::now()->subYears(2),
            'amount' => 1000.00,
            'write_off_period_years' => 5,
        ]);

        static::assertSame(600.00, $item->residual_value);
    }

    public function test_it_returns_zero_when_past_write_off_period(): void
    {
        $item = InventoryItem::factory()->createOne([
            'date' => CarbonImmutable::now()->subYears(10),
            'amount' => 1000.00,
            'write_off_period_years' => 5,
        ]);

        static::assertSame(0.0, $item->residual_value);
    }

    public function test_it_handles_fractional_years(): void
    {
        $item = InventoryItem::factory()->createOne([
            'date' => CarbonImmutable::now()->subMonths(18),
            'amount' => 1000.00,
            'write_off_period_years' => 5,
        ]);

        static::assertSame(600.00, $item->residual_value);
    }
}
