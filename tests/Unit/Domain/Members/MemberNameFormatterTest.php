<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\MemberNameFormatter;
use Tests\UnitTestCase;

final class MemberNameFormatterTest extends UnitTestCase
{
    public function test_it_formats_name_with_infix(): void
    {
        self::assertSame('Doe, John van', MemberNameFormatter::displayName('John', 'van', 'Doe'));
    }

    public function test_it_formats_name_without_infix(): void
    {
        self::assertSame('Doe, John', MemberNameFormatter::displayName('John', null, 'Doe'));
    }

    public function test_it_formats_name_with_empty_infix(): void
    {
        self::assertSame('Doe, John', MemberNameFormatter::displayName('John', '', 'Doe'));
    }
}
