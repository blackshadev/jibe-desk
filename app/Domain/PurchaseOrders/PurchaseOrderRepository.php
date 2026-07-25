<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderRepository
{
    public function markAsPending(PurchaseOrderId $id): void;

    public function markAsPaid(PurchaseOrderIdList $ids): void;

    /**
     * Find an open or pending PurchaseOrder that matches the given debit criteria.
     * Matches on: creditor_iban, amount (within tolerance), date (±30 days).
     * Returns the best match (closest amount), or null.
     */
    public function findMatchingDebit(string $creditorIban, float $amount, DateTimeInterface $date): ?PurchaseOrderId;
}
