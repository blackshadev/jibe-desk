<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

final class CostCenterImportFileException extends CostCenterImportException
{
    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('CSV file not found: %s', $path));
    }

    public static function unreadableFile(string $path): self
    {
        return new self(sprintf('CSV file could not be read: %s', $path));
    }
}
