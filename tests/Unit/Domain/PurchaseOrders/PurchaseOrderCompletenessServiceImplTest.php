<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderCompletenessServiceImpl;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderLineCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class PurchaseOrderCompletenessServiceImplTest extends UnitTestCase
{
    private PurchaseOrderRepositoryExpectation $repo;
    private PurchaseOrderCompletenessServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = PurchaseOrderRepositoryExpectation::create();
        $this->service = new PurchaseOrderCompletenessServiceImpl($this->repo->mock);
    }

    public function test_it_returns_no_problems_for_complete_purchase_orders(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $this->repo->expectsGetCompleteness($ids, [
            new PurchaseOrderCompleteness(
                PurchaseOrderId::create(1),
                'Achmeer',
                'NL02ABNA0123456789',
                [new PurchaseOrderLineCompleteness(7), new PurchaseOrderLineCompleteness(8)],
            ),
        ]);

        $problems = $this->service->findProblems($ids);

        static::assertSame([], $problems->problems);
    }

    /**
     * @return iterable<string, array{?string, ?string, list<PurchaseOrderLineCompleteness>, list<PurchaseOrderProblem>}>
     */
    public static function problemsProvider(): iterable
    {
        yield 'missing creditor name' => [
            null,
            'NL02ABNA0123456789',
            [],
            [
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorName),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingOrderLines),
            ],
        ];

        yield 'blank creditor name counts as missing' => [
            '  ',
            'NL02ABNA0123456789',
            [],
            [
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorName),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingOrderLines),
            ],
        ];

        yield 'blank creditor iban counts as missing' => [
            'Achmeer',
            ' ',
            [],
            [
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorIban),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingOrderLines),
            ],
        ];

        yield 'a purchase order without order lines is a problem' => [
            'Achmeer',
            'NL02ABNA0123456789',
            [],
            [new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingOrderLines)],
        ];

        yield 'every line without a cost center is numbered from one' => [
            'Achmeer',
            'NL02ABNA0123456789',
            [
                new PurchaseOrderLineCompleteness(null),
                new PurchaseOrderLineCompleteness(7),
                new PurchaseOrderLineCompleteness(null),
            ],
            [
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 3),
            ],
        ];

        yield 'nothing is missing' => [
            'Achmeer',
            'NL02ABNA0123456789',
            [new PurchaseOrderLineCompleteness(7)],
            [],
        ];
    }

    /**
     * @param list<PurchaseOrderLineCompleteness> $lines
     * @param list<PurchaseOrderProblem> $expectedProblems
     */
    #[DataProvider('problemsProvider')]
    public function test_it_derives_the_problems_of_a_single_purchase_order(
        ?string $creditorName,
        ?string $creditorIban,
        array $lines,
        array $expectedProblems,
    ): void {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1)]);
        $this->repo->expectsGetCompleteness($ids, [
            new PurchaseOrderCompleteness(PurchaseOrderId::create(1), $creditorName, $creditorIban, $lines),
        ]);

        $problems = $this->service->findProblems($ids);

        static::assertEquals($expectedProblems, $problems->problems);
    }

    public function test_it_collects_the_problems_of_every_purchase_order(): void
    {
        $ids = new PurchaseOrderIdList([PurchaseOrderId::create(1), PurchaseOrderId::create(2)]);
        $this->repo->expectsGetCompleteness($ids, [
            new PurchaseOrderCompleteness(
                PurchaseOrderId::create(1),
                null,
                null,
                [new PurchaseOrderLineCompleteness(null), new PurchaseOrderLineCompleteness(7)],
            ),
            new PurchaseOrderCompleteness(PurchaseOrderId::create(2), null, null, []),
        ]);

        $problems = $this->service->findProblems($ids);

        static::assertEquals(
            [
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorName),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorIban),
                new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1),
                new PurchaseOrderProblem(PurchaseOrderId::create(2), PurchaseOrderProblemType::MissingCreditorName),
                new PurchaseOrderProblem(PurchaseOrderId::create(2), PurchaseOrderProblemType::MissingCreditorIban),
                new PurchaseOrderProblem(PurchaseOrderId::create(2), PurchaseOrderProblemType::MissingOrderLines),
            ],
            $problems->problems,
        );
    }
}
