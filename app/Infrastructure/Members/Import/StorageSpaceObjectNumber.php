<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;

/**
 * An e-Captain object number such as "5-01": container 5, space 1.
 */
final readonly class StorageSpaceObjectNumber
{
    private const string PATTERN = '/^(\d+)-(\d+)$/';

    public function __construct(
        public string $locationName,
        public string $number,
    ) {}

    /**
     * @throws LegacyCsvImportException when the value is not a container-number pair
     */
    public static function from(string $value, int $rowNumber, string $lidnr): self
    {
        $matched = preg_match(self::PATTERN, $value, $matches);

        if ($matched !== 1) {
            throw LegacyCsvImportException::invalidObjectNumber($rowNumber, $lidnr, $value);
        }

        return new self(
            locationName: sprintf('Container %d', (int) $matches[1]),
            number: (string) (int) $matches[2],
        );
    }
}
