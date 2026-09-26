<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Labels;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use App\Filament\Admin\Labels\PurchaseOrderProblemLabels;
use Tests\FeatureTestCase;

final class PurchaseOrderProblemLabelsTest extends FeatureTestCase
{
    public function test_it_describes_a_missing_cost_center_with_the_line_number(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(3), PurchaseOrderProblemType::MissingCostCenter, 2);

        static::assertSame('#3: Regel 2 heeft geen kostenplaats', PurchaseOrderProblemLabels::describe($problem));
    }

    public function test_it_describes_a_missing_creditor_name(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(7), PurchaseOrderProblemType::MissingCreditorName);

        static::assertSame('#7: De crediteursnaam ontbreekt', PurchaseOrderProblemLabels::describe($problem));
    }

    public function test_it_describes_a_missing_creditor_iban(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(12), PurchaseOrderProblemType::MissingCreditorIban);

        static::assertSame('#12: Het IBAN van de crediteur ontbreekt', PurchaseOrderProblemLabels::describe($problem));
    }

    public function test_it_describes_missing_order_lines(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(4), PurchaseOrderProblemType::MissingOrderLines);

        static::assertSame('#4: De inkooporder heeft geen orderregels', PurchaseOrderProblemLabels::describe($problem));
    }

    public function test_it_describes_all_problems_separated_by_newlines(): void
    {
        $problems = [
            new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 4),
            new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorName),
            new PurchaseOrderProblem(PurchaseOrderId::create(5), PurchaseOrderProblemType::MissingCreditorIban),
        ];

        static::assertSame(
            '#1: Regel 4 heeft geen kostenplaats' . PHP_EOL . '#1: De crediteursnaam ontbreekt' . PHP_EOL . '#5: Het IBAN van de crediteur ontbreekt',
            PurchaseOrderProblemLabels::describeAll($problems),
        );
    }

    public function test_it_describes_an_empty_list_as_an_empty_string(): void
    {
        static::assertSame('', PurchaseOrderProblemLabels::describeAll([]));
    }
}
