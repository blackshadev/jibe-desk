<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class PurchaseOrderApprovalStatusTest extends UnitTestCase
{
    /** @return iterable<string, array{PurchaseOrderApprovalStatus, string}> */
    public static function statusProvider(): iterable
    {
        yield 'pending' => [PurchaseOrderApprovalStatus::Pending, 'pending'];
        yield 'accepted' => [PurchaseOrderApprovalStatus::Accepted, 'accepted'];
        yield 'declined' => [PurchaseOrderApprovalStatus::Declined, 'declined'];
    }

    #[DataProvider('statusProvider')]
    public function test_it_has_expected_value(PurchaseOrderApprovalStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->value);
    }
}
