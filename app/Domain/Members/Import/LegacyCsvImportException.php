<?php

declare(strict_types=1);

namespace App\Domain\Members\Import;

use RuntimeException;

abstract class LegacyCsvImportException extends RuntimeException
{
    protected function __construct(
        string $message,
        public readonly ?int $rowNumber = null,
        public readonly ?string $lidnr = null,
    ) {
        parent::__construct($message);
    }

    protected static function rowMessage(int $rowNumber, string $reason, ?string $lidnr = null): string
    {
        $context = $lidnr === null
            ? sprintf('Row %d', $rowNumber)
            : sprintf('Row %d (lidnr %s)', $rowNumber, $lidnr);

        return sprintf('%s: %s', $context, $reason);
    }
}
