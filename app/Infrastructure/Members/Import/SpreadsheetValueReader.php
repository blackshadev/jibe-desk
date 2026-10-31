<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;

final readonly class SpreadsheetValueReader
{
    /** @param array<string, mixed> $values */
    public function text(array $values, string $column): string
    {
        $value = $values[$column] ?? '';

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /** @param array<string, mixed> $values */
    public function requiredText(array $values, string $column, int $rowNumber): string
    {
        $value = $this->text($values, $column);

        if ($value === '') {
            throw MemberImportException::missingValue($rowNumber, $column);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    public function optionalText(array $values, string $column): ?string
    {
        $value = $this->text($values, $column);

        return $value === '' ? null : $value;
    }

    public function email(string $privateEmail, ?string $contactEmail, int $rowNumber): string
    {
        if ($privateEmail !== '') {
            return $privateEmail;
        }

        if ($contactEmail !== null) {
            return $contactEmail;
        }

        throw MemberImportException::missingValue($rowNumber, 'E-mail privé');
    }

    public function fullName(string $fullName, string $firstName, string $infixName, string $lastName): string
    {
        if ($fullName !== '') {
            return $fullName;
        }

        $parts = array_filter(
            [$firstName, $infixName, $lastName],
            static fn (string $part): bool => $part !== '',
        );

        return trim(implode(' ', $parts));
    }

    public function accountHolderName(string $accountHolderName, string $fullName): string
    {
        return $accountHolderName === '' ? $fullName : $accountHolderName;
    }
}
