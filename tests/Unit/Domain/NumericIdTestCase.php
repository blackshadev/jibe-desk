<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\NumericId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

abstract class NumericIdTestCase extends TestCase
{
    public function test__construct(): void
    {
        $cls = $this->getSubject();
        $subject = new $cls(1);

        self::assertSame(1, $subject->value);
    }

    public function test_throws_exception_when_id_is_negative(): void
    {
        $cls = $this->getSubject();

        $this->expectException(InvalidArgumentException::class);

        new $cls(-1);
    }

    /** @return class-string<NumericId> */
    abstract protected function getSubject(): string;
}
