<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Gender;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\UnitTestCase;

final class GenderTest extends UnitTestCase
{
    #[TestWith(['Male', 'M'])]
    #[TestWith(['Female', 'F'])]
    #[TestWith(['NonBinary', 'NB'])]
    #[TestWith(['Unknown', 'U'])]
    #[TestWith(['Other', 'O'])]
    public function test_all_cases_have_expected_values(string $caseName, string $value): void
    {
        $case = Gender::from($value);

        static::assertSame($value, $case->value);
        static::assertSame($caseName, $case->name);
        static::assertSame($value, $case->value);
    }

    public function test_values_cast_to_string(): void
    {
        static::assertSame('M', Gender::Male->value);
        static::assertSame('F', Gender::Female->value);
        static::assertSame('NB', Gender::NonBinary->value);
        static::assertSame('U', Gender::Unknown->value);
        static::assertSame('O', Gender::Other->value);
    }
}
