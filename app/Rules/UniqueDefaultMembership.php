<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Membership;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

final readonly class UniqueDefaultMembership implements ValidationRule
{
    public function __construct(
        private ?int $excludeId = null,
    ) {}

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === false) {
            return;
        }

        $query = Membership::query()->where('is_default', true);

        if ($this->excludeId !== null) {
            $query->where('id', '!=', $this->excludeId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique_default_membership'));
        }
    }
}
