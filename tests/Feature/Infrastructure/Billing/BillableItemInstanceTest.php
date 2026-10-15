<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing;

use App\Domain\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Member;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

final class BillableItemInstanceTest extends FeatureTestCase
{
    /** @return iterable<string, array{int, int, string, string, float, float}> */
    public static function quantityProvider(): iterable
    {
        yield 'monthly returns full quantity' => [1, 1, '2026-01-01', '2026-08-15', 1.0, 0.0];
        yield 'annual bill month 1 start august when august' => [12, 1, '2026-08-15', '2026-08-01', 5.0 / 12.0, 0.0001];
        yield 'annual bill month 1 start january when january' => [12, 1, '2026-01-15', '2026-01-20', 1.0, 0.0];
        yield 'annual bill month 1 start august when next january' => [12, 1, '2026-08-15', '2027-01-01', 1.0, 0.0];
        yield 'quarterly bill month 1 start february when february' => [3, 1, '2026-02-01', '2026-02-15', 2.0 / 3.0, 0.0001];
        yield 'quarterly bill month 1 start january when april' => [3, 1, '2026-01-01', '2026-04-01', 1.0, 0.0];
        yield 'annual bill month 7 start august when august' => [12, 7, '2026-08-01', '2026-08-01', 11.0 / 12.0, 0.0001];
        yield 'annual start before anchor' => [12, 1, '2026-01-01', '2026-08-01', 1.0, 0.0];
        yield 'annual start before start_date' => [12, 1, '2027-01-01', '2026-08-01', 0.0, 0.0];
        yield 'quarterly start before start_date' => [3, 1, '2027-01-01', '2026-08-01', 0.0, 0.0];
    }

    #[DataProvider('quantityProvider')]
    public function test_quantity_for(int $billCycleInMonths, int $billMonth, string $startDate, string $when, float $expectedQuantity, float $delta): void
    {
        $instance = BillableItemInstance::factory()
            ->for(Member::factory()->createQuietly())
            ->for(BillableItem::factory()->state([
                'bill_period' => BillPeriod::Annually,
                'prorate' => true,
            ]))
            ->create([
                'bill_cycle_in_months' => $billCycleInMonths,
                'bill_month' => $billMonth,
                'start_date' => $startDate,
            ]);

        $quantity = $instance->quantityFor(new DateTimeImmutable($when));

        static::assertEqualsWithDelta($expectedQuantity, $quantity, $delta);
    }

    public function test_quantity_for_returns_full_quantity_when_item_is_not_prorated(): void
    {
        $instance = BillableItemInstance::factory()
            ->for(Member::factory()->createQuietly())
            ->for(BillableItem::factory()->state([
                'bill_period' => BillPeriod::Annually,
                'prorate' => false,
            ]))
            ->create([
                'bill_cycle_in_months' => 12,
                'bill_month' => 1,
                'start_date' => '2026-08-15',
            ]);

        $quantity = $instance->quantityFor(new DateTimeImmutable('2026-08-15'));

        static::assertSame(1.0, $quantity);
    }

    public function test_quantity_for_returns_full_quantity_for_once_items(): void
    {
        $instance = BillableItemInstance::factory()
            ->for(Member::factory()->createQuietly())
            ->for(BillableItem::factory()->state([
                'bill_period' => BillPeriod::Once,
                'prorate' => true,
            ]))
            ->create([
                'bill_cycle_in_months' => BillPeriod::Once->toBillPeriodInMonths(),
                'bill_month' => 1,
                'start_date' => '2026-08-15',
            ]);

        $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-15'));

        static::assertSame(1.0, $quantity);
    }

    public function test_quantity_for_returns_zero_before_start_for_once_items(): void
    {
        $instance = BillableItemInstance::factory()
            ->for(Member::factory()->createQuietly())
            ->for(BillableItem::factory()->state([
                'bill_period' => BillPeriod::Once,
                'prorate' => false,
            ]))
            ->create([
                'bill_cycle_in_months' => BillPeriod::Once->toBillPeriodInMonths(),
                'bill_month' => 1,
                'start_date' => '2027-01-01',
            ]);

        $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-01'));

        static::assertSame(0.0, $quantity);
    }

    public function test_quantity_for_returns_zero_before_start_even_when_not_prorated(): void
    {
        $instance = BillableItemInstance::factory()
            ->for(Member::factory()->createQuietly())
            ->for(BillableItem::factory()->state([
                'bill_period' => BillPeriod::Annually,
                'prorate' => false,
            ]))
            ->create([
                'bill_cycle_in_months' => 12,
                'bill_month' => 1,
                'start_date' => '2027-01-01',
            ]);

        $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-01'));

        static::assertSame(0.0, $quantity);
    }
}
