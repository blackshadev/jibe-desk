<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderRepository
{
    /**
     * Load the facts needed to check purchase order completeness for a status transition.
     *
     * @return list<PurchaseOrderCompleteness>
     */
    public function getCompleteness(PurchaseOrderIdList $ids): array;

    public function markAsApprovalAccepted(PurchaseOrderIdList $ids): void;

    public function markAsApprovalDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void;

    public function markAsPaymentPending(PurchaseOrderIdList $ids): void;

    public function markAsPaid(PurchaseOrderIdList $ids): void;

    public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void;

    public function reopenPayment(PurchaseOrderIdList $ids): void;

    public function hasCompletedTransactions(PurchaseOrderId $id): bool;

    /**
     * Find an open or pending PurchaseOrder that matches the given debit criteria.
     * Matches on: creditor_iban, amount (within tolerance), date (±30 days).
     * Returns the best match (closest amount), or null.
     */
    public function findMatchingDebit(string $creditorIban, float $amount, DateTimeInterface $date): ?PurchaseOrderId;
}
