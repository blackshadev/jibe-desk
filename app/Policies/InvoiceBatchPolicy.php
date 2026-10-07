<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Models\InvoiceBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class InvoiceBatchPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'invoice_batches';
    }

    public function delete(User $user, Model $batch): bool
    {
        Assert::isInstanceOf($batch, InvoiceBatch::class);

        if ($batch->status !== InvoiceBatchStatus::Open) {
            return false;
        }

        return parent::delete($user, $batch);
    }
}
