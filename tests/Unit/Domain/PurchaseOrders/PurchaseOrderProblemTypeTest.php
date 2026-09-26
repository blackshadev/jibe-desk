<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use Tests\UnitTestCase;

final class PurchaseOrderProblemTypeTest extends UnitTestCase
{
    public function test_it_has_missing_cost_center_type(): void
    {
        static::assertSame('missing_cost_center', PurchaseOrderProblemType::MissingCostCenter->value);
    }

    public function test_it_has_missing_creditor_name_type(): void
    {
        static::assertSame('missing_creditor_name', PurchaseOrderProblemType::MissingCreditorName->value);
    }

    public function test_it_has_missing_creditor_iban_type(): void
    {
        static::assertSame('missing_creditor_iban', PurchaseOrderProblemType::MissingCreditorIban->value);
    }

    public function test_it_has_exactly_three_cases(): void
    {
        static::assertCount(3, PurchaseOrderProblemType::cases());
    }

    public function test_it_resolves_a_case_from_its_value(): void
    {
        static::assertSame(
            PurchaseOrderProblemType::MissingCreditorIban,
            PurchaseOrderProblemType::from('missing_creditor_iban'),
        );
    }

    public function test_it_try_from_returns_null_for_an_unknown_value(): void
    {
        static::assertNull(PurchaseOrderProblemType::tryFrom('unknown_problem'));
    }
}
