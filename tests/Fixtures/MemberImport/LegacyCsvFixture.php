<?php

declare(strict_types=1);

namespace Tests\Fixtures\MemberImport;

use RuntimeException;

final class LegacyCsvFixture
{
    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public static function create(array $headers, array $rows): string
    {
        $path = sys_get_temp_dir() . '/legacy-csv-' . bin2hex(random_bytes(8)) . '.csv';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not create %s', $path));
        }

        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);

        return $path;
    }
}
