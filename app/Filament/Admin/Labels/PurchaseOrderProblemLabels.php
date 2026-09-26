<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;

final class PurchaseOrderProblemLabels
{
    public static function describe(PurchaseOrderProblem $problem): string
    {
        $label = match ($problem->type) {
            PurchaseOrderProblemType::MissingCostCenter => __('labels.problem_missing_cost_center', ['line' => $problem->lineNumber]),
            PurchaseOrderProblemType::MissingCreditorName => __('labels.problem_missing_creditor_name'),
            PurchaseOrderProblemType::MissingCreditorIban => __('labels.problem_missing_creditor_iban'),
            PurchaseOrderProblemType::MissingOrderLines => __('labels.problem_missing_order_lines'),
        };

        return sprintf('#%d: %s', $problem->purchaseOrderId->value, $label);
    }

    /** @param list<PurchaseOrderProblem> $problems */
    public static function describeAll(array $problems): string
    {
        return implode(PHP_EOL, array_map(self::describe(...), $problems));
    }
}
