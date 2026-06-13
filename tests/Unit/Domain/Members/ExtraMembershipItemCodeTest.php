<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\ExtraMembershipItemCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class ExtraMembershipItemCodeTest extends UnitTestCase
{
    /** @return iterable<string, array{ExtraMembershipItemCode, string}> */
    public static function codeProvider(): iterable
    {
        yield 'restitution' => [ExtraMembershipItemCode::VolunteerRestitution, 'vrijwilliger_restitutie'];
        yield 'contribution' => [ExtraMembershipItemCode::VolunteerContribution, 'vrijwilligers_bijdrage'];
    }

    #[DataProvider('codeProvider')]
    public function test_it_has_expected_value(ExtraMembershipItemCode $code, string $expected): void
    {
        static::assertSame($expected, $code->value);
    }
}
