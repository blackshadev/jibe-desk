<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class PurchaseOrderStatusTest extends UnitTestCase
{
    /** @return iterable<string, array{PurchaseOrderStatus, string}> */
    public static function statusProvider(): iterable
    {
        yield 'open' => [PurchaseOrderStatus::Open, 'open'];
        yield 'pending' => [PurchaseOrderStatus::Pending, 'pending'];
        yield 'paid' => [PurchaseOrderStatus::Paid, 'paid'];
        yield 'declined' => [PurchaseOrderStatus::Declined, 'declined'];
        yield 'cancelled' => [PurchaseOrderStatus::Cancelled, 'cancelled'];
    }

    #[DataProvider('statusProvider')]
    public function test_it_has_expected_value(PurchaseOrderStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->value);
    }
}
