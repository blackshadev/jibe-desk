<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Infrastructure\PurchaseOrders\PurchaseOrderRepositoryDb;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;

final class PurchaseOrderRepositoryDbMatchingTest extends FeatureTestCase
{
    private PurchaseOrderRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PurchaseOrderRepositoryDb();
    }

    public function test_it_finds_matching_debit_with_exact_match(): void
    {
        $iban = 'NL91ABNA0417164300';

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertInstanceOf(PurchaseOrderId::class, $result);
        static::assertEquals($purchaseOrder->id, $result->value);
    }

    public function test_it_returns_null_for_wrong_iban(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => 'NL91ABNA0417164300',
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit('NL91ABNA0417164999', 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_returns_null_for_wrong_amount(): void
    {
        $iban = 'NL91ABNA0417164300';

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 200.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_returns_null_for_date_outside_30_days(): void
    {
        $iban = 'NL91ABNA0417164300';

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-01',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 100.00, new DateTimeImmutable('2026-03-01'));

        static::assertNull($result);
    }

    public function test_it_ignores_paid_purchase_orders(): void
    {
        $iban = 'NL91ABNA0417164300';

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Paid,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertNull($result);
    }

    public function test_it_finds_pending_purchase_orders(): void
    {
        $iban = 'NL91ABNA0417164300';

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Pending,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertInstanceOf(PurchaseOrderId::class, $result);
        static::assertEquals($purchaseOrder->id, $result->value);
    }

    public function test_it_picks_closest_amount_when_multiple_candidates(): void
    {
        $iban = 'NL91ABNA0417164300';

        $po1 = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-15',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po1->id,
            'price' => 90.00,
        ]);

        $po2 = PurchaseOrder::factory()->create([
            'creditor_iban' => $iban,
            'status' => PurchaseOrderStatus::Open,
            'date' => '2026-01-16',
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po2->id,
            'price' => 100.00,
        ]);

        $result = $this->repository->findMatchingDebit($iban, 100.00, new DateTimeImmutable('2026-01-15'));

        static::assertInstanceOf(PurchaseOrderId::class, $result);
        static::assertEquals($po2->id, $result->value);
    }
}
