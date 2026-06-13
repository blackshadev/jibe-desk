<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activities;

use App\Domain\Activities\ActivityId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class ActivityIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return ActivityId::class;
    }
}
