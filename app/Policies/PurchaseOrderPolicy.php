<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

final class PurchaseOrderPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'purchase_orders';
    }

    public function update(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        return $user->can('update_purchase_orders') && $purchaseOrder->status === PurchaseOrderStatus::Open;
    }

    public function delete(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        return $user->can('delete_purchase_orders') && $purchaseOrder->status === PurchaseOrderStatus::Open;
    }
}
