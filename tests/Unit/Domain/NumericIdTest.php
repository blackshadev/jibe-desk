<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\NumericId;

final readonly class StubId extends NumericId
{
}

final class NumericIdTest extends NumericIdTestCase
{
    protected function getSubject(): string
    {
        return StubId::class;
    }
}
