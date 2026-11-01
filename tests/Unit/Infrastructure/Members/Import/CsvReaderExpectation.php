<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Infrastructure\Members\Import\CsvReader;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class CsvReaderExpectation
{
    private function __construct(
        public MockInterface&CsvReader $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(CsvReader::class));
    }

    public function expectsRequiredText(SpreadsheetImportRow $row, string $column, string $result): void
    {
        $this->mock
            ->expects('requiredText')
            ->with(equalTo($row), equalTo($column))
            ->andReturn($result);
    }

    public function expectsRequiredTextForMember(
        SpreadsheetImportRow $row,
        string $column,
        string $memberNumber,
        string $result,
    ): void {
        $this->mock
            ->expects('requiredText')
            ->with(equalTo($row), equalTo($column), equalTo($memberNumber))
            ->andReturn($result);
    }

    public function expectsRequiredDate(
        SpreadsheetImportRow $row,
        string $column,
        string $memberNumber,
        DateTimeImmutable $result,
    ): void {
        $this->mock
            ->expects('requiredDate')
            ->with(equalTo($row), equalTo($column), equalTo($memberNumber))
            ->andReturn($result);
    }

    public function expectsOptionalDate(
        SpreadsheetImportRow $row,
        string $column,
        string $memberNumber,
        ?DateTimeImmutable $result,
    ): void {
        $this->mock
            ->expects('optionalDate')
            ->with(equalTo($row), equalTo($column), equalTo($memberNumber))
            ->andReturn($result);
    }
}
