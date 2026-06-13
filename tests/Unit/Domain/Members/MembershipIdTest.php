<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MembershipId;
use Tests\Unit\Domain\NumericIdTestCase;
use Override;

final class MembershipIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return MembershipId::class;
    }
}
