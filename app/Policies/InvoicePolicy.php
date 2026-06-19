<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

final class InvoicePolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'invoices';
    }

    public function update(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('update_invoices') && $invoice->status === InvoiceStatus::Open;
    }

    public function delete(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('delete_invoices') && $invoice->status === InvoiceStatus::Open;
    }
}
