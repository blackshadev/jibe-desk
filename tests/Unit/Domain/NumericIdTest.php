<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\NumericId;
use Override;

final class NumericIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return StubId::class;
    }
}

// @mago-expect lint:single-class-per-file
final readonly class StubId extends NumericId {}
