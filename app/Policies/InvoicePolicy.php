<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class InvoicePolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'invoices';
    }

    #[Override]
    public function update(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('update_invoices') && $invoice->status === InvoiceStatus::Open;
    }

    public function createCredit(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return (
            $user->can('create_invoices')
            && $invoice->creditInvoice()->doesntExist()
            && $invoice->credit_invoice_id === null
            && in_array($invoice->status, [InvoiceStatus::Pending, InvoiceStatus::Paid, InvoiceStatus::Declined], true)
        );
    }

    public function markDeclined(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('update_invoices', $invoice) && $invoice->status === InvoiceStatus::Pending;
    }

    public function markPaid(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('update_invoices', $invoice) && $invoice->status === InvoiceStatus::Pending;
    }

    public function markPending(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('update_invoices', $invoice) && $invoice->status === InvoiceStatus::Open;
    }

    #[Override]
    public function delete(User $user, Model $invoice): bool
    {
        Assert::isInstanceOf($invoice, Invoice::class);
        return $user->can('delete_invoices') && $invoice->status === InvoiceStatus::Open;
    }
}
