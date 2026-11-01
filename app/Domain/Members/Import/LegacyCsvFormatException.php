<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

final class LegacyCsvFormatException extends LegacyCsvImportException
{
    /**
     * @param list<string> $missingHeaders
     */
    public static function headerMismatch(array $missingHeaders): self
    {
        return new self(sprintf('CSV is missing required columns: %s', implode(', ', $missingHeaders)));
    }

    public static function missingValue(int $rowNumber, string $column, ?string $lidnr = null): self
    {
        return new self(
            self::rowMessage($rowNumber, sprintf('column "%s" is required but empty.', $column), $lidnr),
            $rowNumber,
            $lidnr,
        );
    }

    public static function invalidDate(int $rowNumber, string $column, string $value, ?string $lidnr = null): self
    {
        return new self(
            self::rowMessage($rowNumber, sprintf('column "%s", value "%s" is not a valid Y-m-d date.', $column, $value), $lidnr),
            $rowNumber,
            $lidnr,
        );
    }
}
