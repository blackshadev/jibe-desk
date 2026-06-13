<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MemberId;
use Override;
use Tests\Unit\Domain\NumericIdTestCase;

final class MemberIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return MemberId::class;
    }
}
