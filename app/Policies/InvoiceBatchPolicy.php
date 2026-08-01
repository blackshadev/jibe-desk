<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class InvoiceBatchPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'invoice_batches';
    }
}
