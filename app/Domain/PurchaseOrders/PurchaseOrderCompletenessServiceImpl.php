<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use Override;

final readonly class PurchaseOrderCompletenessServiceImpl implements PurchaseOrderCompletenessService
{
    public function __construct(
        private PurchaseOrderRepository $repository,
    ) {}

    #[Override]
    public function findProblems(PurchaseOrderIdList $ids): PurchaseOrderProblems
    {
        return new PurchaseOrderProblems(
            array_merge(
                [],
                ...array_map(
                    self::problemsFor(...),
                    $this->repository->getCompleteness($ids),
                ),
            ),
        );
    }

    /**
     * The problems of a single purchase order, derived functionally from its completeness facts.
     *
     * @return list<PurchaseOrderProblem>
     */
    private static function problemsFor(PurchaseOrderCompleteness $completeness): array
    {
        $missingCreditorName = $completeness->creditorName === null || trim($completeness->creditorName) === '';
        $missingCreditorIban = $completeness->creditorIban === null || trim($completeness->creditorIban) === '';

        return array_merge(
            $missingCreditorName
                ? [new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::MissingCreditorName)]
                : [],
            $missingCreditorIban
                ? [new PurchaseOrderProblem($completeness->id, PurchaseOrderProblemType::MissingCreditorIban)]
                : [],
            self::problemsForLines($completeness),
        );
    }

    /**
     * @return list<PurchaseOrderProblem>
     */
    private static function problemsForLines(PurchaseOrderCompleteness $completeness): array
    {
        $lineNumbersWithoutCostCenter = array_keys(
            array_filter(
                $completeness->lines,
                static fn (PurchaseOrderLineCompleteness $line): bool => $line->costCenterId === null,
            ),
        );

        return array_map(
            static fn (int $lineNumber): PurchaseOrderProblem => new PurchaseOrderProblem(
                $completeness->id,
                PurchaseOrderProblemType::MissingCostCenter,
                $lineNumber + 1,
            ),
            $lineNumbersWithoutCostCenter,
        );
    }
}
