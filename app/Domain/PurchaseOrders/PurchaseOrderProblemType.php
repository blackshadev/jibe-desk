<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

enum PurchaseOrderProblemType: string
{
    case MissingCostCenter = 'missing_cost_center';
    case MissingCreditorName = 'missing_creditor_name';
    case MissingCreditorIban = 'missing_creditor_iban';
}
