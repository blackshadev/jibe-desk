<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CostCenterGroup;

final class CostCenterGroupObserver
{
    public function updated(CostCenterGroup $costCenterGroup): void
    {
        if (!$costCenterGroup->wasChanged('number')) {
            return;
        }

        $oldNumber = (string) $costCenterGroup->getOriginal('number');
        $newNumber = $costCenterGroup->number;

        $descendants = CostCenterGroup::query()
            ->where('number', 'like', $oldNumber . '.%')
            ->orderBy('number')
            ->get();

        foreach ($descendants as $descendant) {
            $descendant->number = $newNumber . substr($descendant->number, strlen($oldNumber));
            $descendant->saveQuietly();
        }
    }
}
