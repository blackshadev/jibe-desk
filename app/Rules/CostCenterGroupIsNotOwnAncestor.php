<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\CostCenterGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

final readonly class CostCenterGroupIsNotOwnAncestor implements ValidationRule
{
    public function __construct(
        private ?CostCenterGroup $group,
    ) {}

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->group === null || $value === null) {
            return;
        }

        if (!in_array((int) $value, $this->group->selfAndDescendantIds(), true)) {
            return;
        }

        $fail(__('labels.cost_center_group_cycle'));
    }
}
