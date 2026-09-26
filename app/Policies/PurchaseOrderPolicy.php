<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class PurchaseOrderPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'purchase_orders';
    }

    #[Override]
    public function update(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Open) {
            return false;
        }

        return $user->can('update_purchase_orders') || $purchaseOrder->member_id === $user->id;
    }

    #[Override]
    public function delete(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Open) {
            return false;
        }

        return $user->can('delete_purchase_orders') || $purchaseOrder->member_id === $user->id;
    }

    public function markAsPending(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Open) {
            return false;
        }

        return $user->can('update_purchase_orders');
    }

    public function markAsPaid(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return false;
        }

        return $user->can('update_purchase_orders');
    }

    public function markAsDeclined(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        if ($purchaseOrder->status === PurchaseOrderStatus::Paid || $purchaseOrder->status === PurchaseOrderStatus::Declined) {
            return false;
        }

        return $user->can('update_purchase_orders');
    }
}
