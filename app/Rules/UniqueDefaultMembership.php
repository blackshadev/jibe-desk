<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Membership;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class UniqueDefaultMembership implements ValidationRule
{
    public function __construct(
        private ?int $excludeId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $query = Membership::where('is_default', true);

        if ($this->excludeId !== null) {
            $query->where('id', '!=', $this->excludeId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique_default_membership'));
        }
    }
}
