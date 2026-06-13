<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $_user): bool
    {
        return true;
    }

    public function view(User $_user, Invoice $_invoice): bool
    {
        return true;
    }

    public function create(User $_user): bool
    {
        return true;
    }

    public function update(User $_user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open;
    }

    public function delete(User $_user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open;
    }
}
