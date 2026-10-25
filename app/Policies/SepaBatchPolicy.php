<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class SepaBatchPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'sepa_batches';
    }

    #[Override]
    public function delete(User $user, Model $batch): bool
    {
        Assert::isInstanceOf($batch, SepaBatch::class);

        if ($batch->status !== SepaBatchStatus::Open) {
            return false;
        }

        return parent::delete($user, $batch);
    }

    public function export(User $user, Model $batch): bool
    {
        Assert::isInstanceOf($batch, SepaBatch::class);

        return $user->can('update_sepa_batches') && $batch->status === SepaBatchStatus::Pending;
    }

    public function markSubmitted(User $user, Model $batch): bool
    {
        Assert::isInstanceOf($batch, SepaBatch::class);

        return $user->can('update_sepa_batches') && $batch->status === SepaBatchStatus::Pending && $batch->exported_at !== null && $batch->submitted_at === null;
    }
}
