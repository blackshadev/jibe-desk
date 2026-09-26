<?php

declare(strict_types=1);

namespace App\Infrastructure\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderLineCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;

final class PurchaseOrderRepositoryDb implements PurchaseOrderRepository
{
    private const float AMOUNT_TOLERANCE = 0.01;

    #[Override]
    public function getCompleteness(PurchaseOrderIdList $ids): array
    {
        return PurchaseOrder::query()
            ->with('lines')
            ->whereIn('id', $ids->values())
            ->get()
            ->map(static fn (PurchaseOrder $purchaseOrder): PurchaseOrderCompleteness => new PurchaseOrderCompleteness(
                id: PurchaseOrderId::create($purchaseOrder->id),
                creditorName: $purchaseOrder->creditor_name,
                creditorIban: $purchaseOrder->creditor_iban,
                lines: $purchaseOrder
                    ->lines
                    ->map(static fn (PurchaseOrderLine $line) => new PurchaseOrderLineCompleteness(
                        costCenterId: $line->cost_center_id,
                    ))
                    ->values()
                    ->all(),
            ))
            ->all();
    }

    #[Override]
    public function markAsPending(PurchaseOrderIdList $ids): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update([
                'status' => PurchaseOrderStatus::Pending,
                'declined_reason' => null,
            ]);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderIdList $ids): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update(['status' => PurchaseOrderStatus::Paid]);
    }

    #[Override]
    public function markAsDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update([
                'status' => PurchaseOrderStatus::Declined,
                'declined_reason' => $reason,
            ]);
    }

    #[Override]
    public function findMatchingDebit(string $creditorIban, float $amount, DateTimeInterface $date): ?PurchaseOrderId
    {
        $startDate = CarbonImmutable::instance($date)->subDays(30);
        $endDate = CarbonImmutable::instance($date)->addDays(30);

        $purchaseOrder = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrderStatus::Open, PurchaseOrderStatus::Pending])
            ->whereBetween('date', [$startDate, $endDate])
            ->where('creditor_iban', $creditorIban)
            ->orderByRelevancy($amount, $creditorIban)
            ->with('lines')
            ->first();

        if ($purchaseOrder === null) {
            return null;
        }

        if (abs($purchaseOrder->total->price - $amount) > self::AMOUNT_TOLERANCE) {
            return null;
        }

        return PurchaseOrderId::create($purchaseOrder->id);
    }
}
