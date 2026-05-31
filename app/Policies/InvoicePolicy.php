<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open;
    }
}
