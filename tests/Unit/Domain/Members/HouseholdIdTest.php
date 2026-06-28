<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\HouseholdId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class HouseholdIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return HouseholdId::class;
    }
}
