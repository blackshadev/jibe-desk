<?php

declare(strict_types=1);

namespace App\Policies;

final class InvoiceBatchPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'invoice_batches';
    }
}
