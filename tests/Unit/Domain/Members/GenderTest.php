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
    #[TestWith(['Undetermined', 'U'])]
    #[TestWith(['Other', 'O'])]
    public function test_all_cases_have_expected_values(string $caseName, string $value): void
    {
        $case = Gender::from($value);

        self::assertSame($value, $case->value);
        self::assertSame($caseName, $case->name);
        self::assertSame($value, $case->value);
    }

    public function test_values_cast_to_string(): void
    {
        self::assertSame('M', Gender::Male->value);
        self::assertSame('F', Gender::Female->value);
        self::assertSame('NB', Gender::NonBinary->value);
        self::assertSame('U', Gender::Undetermined->value);
        self::assertSame('O', Gender::Other->value);
    }
}
