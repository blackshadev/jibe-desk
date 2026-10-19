<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Infrastructure\Billing\PurchaseOrders\PurchaseOrderRepositoryDb;
use App\Models\PurchaseOrder;
use Override;
use Tests\FeatureTestCase;

final class PurchaseOrderRepositoryDbTest extends FeatureTestCase
{
    private PurchaseOrderRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PurchaseOrderRepositoryDb();
    }

    public function test_mark_as_approval_accepted_updates_approval_and_clears_declined_reason(): void
    {
        $purchaseOrder = PurchaseOrder::factory()
            ->open()
            ->createQuietly([
                'declined_reason' => 'Oude reden',
            ]);

        $this->repository->markAsApprovalAccepted(PurchaseOrderIdList::fromArray([$purchaseOrder->id]));

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderApprovalStatus::Accepted, $purchaseOrder->approval_status);
        static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->status);
        static::assertNull($purchaseOrder->declined_reason);
    }

    public function test_mark_as_approval_declined_cancels_the_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->open()->createQuietly();

        $this->repository->markAsApprovalDeclined(
            PurchaseOrderIdList::fromArray([$purchaseOrder->id]),
            'Niet geautoriseerd door penningmeester',
        );

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderApprovalStatus::Declined, $purchaseOrder->approval_status);
        static::assertSame(PurchaseOrderStatus::Cancelled, $purchaseOrder->status);
        static::assertSame('Niet geautoriseerd door penningmeester', $purchaseOrder->declined_reason);
    }

    public function test_mark_as_payment_pending_updates_only_the_payment_status(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->accepted()->createQuietly();

        $this->repository->markAsPaymentPending(PurchaseOrderIdList::fromArray([$purchaseOrder->id]));

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderStatus::Pending, $purchaseOrder->status);
        static::assertSame(PurchaseOrderApprovalStatus::Accepted, $purchaseOrder->approval_status);
    }

    public function test_mark_as_payment_declined_keeps_the_approval_and_stores_the_reason(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->pending()->createQuietly();

        $this->repository->markAsPaymentDeclined(
            PurchaseOrderIdList::fromArray([$purchaseOrder->id]),
            'Onvoldoende saldo',
        );

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderStatus::Declined, $purchaseOrder->status);
        static::assertSame(PurchaseOrderApprovalStatus::Accepted, $purchaseOrder->approval_status);
        static::assertSame('Onvoldoende saldo', $purchaseOrder->declined_reason);
    }

    public function test_reopen_payment_sets_the_status_back_to_open(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->declined()->createQuietly();

        $this->repository->reopenPayment(PurchaseOrderIdList::fromArray([$purchaseOrder->id]));

        $purchaseOrder->refresh();
        static::assertSame(PurchaseOrderStatus::Open, $purchaseOrder->status);
        static::assertSame(PurchaseOrderApprovalStatus::Accepted, $purchaseOrder->approval_status);
    }
}
