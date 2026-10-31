<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class SpreadsheetDateReader
{
    /** @param array<string, mixed> $values */
    public function date(array $values, string $column, int $rowNumber): DateTimeImmutable
    {
        $date = $this->read($values, $column);

        if ($date === null) {
            throw MemberImportException::missingValue($rowNumber, $column);
        }

        return $this->parse($date, $rowNumber, $column);
    }

    /** @param array<string, mixed> $values */
    public function optionalDate(array $values, string $column, int $rowNumber): ?DateTimeImmutable
    {
        $date = $this->read($values, $column);

        return $date === null ? null : $this->parse($date, $rowNumber, $column);
    }

    private function parse(string $date, int $rowNumber, string $column): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false) {
            throw MemberImportException::invalidDate($rowNumber, $column, $date);
        }

        return $parsed;
    }

    /** @param array<string, mixed> $values */
    private function read(array $values, string $column): ?string
    {
        $value = $values[$column] ?? null;

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTime(0, 0)->format('Y-m-d');
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
