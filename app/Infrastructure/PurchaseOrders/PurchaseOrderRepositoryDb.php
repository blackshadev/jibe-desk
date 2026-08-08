<?php

declare(strict_types=1);

namespace App\Infrastructure\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;

final class PurchaseOrderRepositoryDb implements PurchaseOrderRepository
{
    private const float AMOUNT_TOLERANCE = 0.01;

    #[Override]
    public function markAsPending(PurchaseOrderIdList $ids): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update(['status' => PurchaseOrderStatus::Pending]);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderIdList $ids): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update(['status' => PurchaseOrderStatus::Paid]);
    }

    #[Override]
    public function markAsDeclined(PurchaseOrderIdList $ids): void
    {
        PurchaseOrder::query()
            ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
            ->update(['status' => PurchaseOrderStatus::Declined]);
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
