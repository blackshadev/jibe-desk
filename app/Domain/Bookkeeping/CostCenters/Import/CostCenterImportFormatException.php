<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

final class CostCenterImportFormatException extends CostCenterImportException
{
    /**
     * @param list<string> $expectedHeaders
     */
    public static function headerMismatch(string $path, array $expectedHeaders): self
    {
        return new self(sprintf(
            'File "%s" does not have the expected header: %s',
            $path,
            implode(';', $expectedHeaders),
        ));
    }

    public static function emptyFile(string $path): self
    {
        return new self(sprintf('File "%s" is empty.', $path));
    }

    public static function missingValue(string $path, int $lineNumber, string $column): self
    {
        return new self(sprintf(
            'File "%s", line %d: column "%s" is required but empty.',
            $path,
            $lineNumber,
            $column,
        ));
    }

    /**
     * @param list<string> $numbers
     */
    public static function duplicateAccountNumbers(array $numbers): self
    {
        return new self(sprintf(
            'The grootboek file contains duplicate account numbers: %s',
            implode(', ', self::uniqueSorted($numbers)),
        ));
    }

    /**
     * @param list<string> $numbers
     */
    public static function duplicateGroupNumbers(array $numbers): self
    {
        return new self(sprintf(
            'A group file contains duplicate numbers: %s',
            implode(', ', self::uniqueSorted($numbers)),
        ));
    }

    /**
     * @param list<string> $numbers
     * @return list<string>
     */
    private static function uniqueSorted(array $numbers): array
    {
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }
}
