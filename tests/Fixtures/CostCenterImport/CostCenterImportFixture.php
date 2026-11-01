<?php

declare(strict_types=1);

namespace Tests\Fixtures\CostCenterImport;

use RuntimeException;

final class CostCenterImportFixture
{
    public const array GROOTBOEK_HEADERS = [
        'Rekening',
        'Omschrijving',
        'B/W',
        'O/K/N',
        'D/C',
        'BTW',
        'Nr   ',
        'Verdichting',
    ];

    public const array VERDICHTING_HEADERS = [
        'Nummer ',
        'Verdichting',
        '',
        'Hoofdverdichting',
    ];

    public const array HOOFDVERDICHTING_HEADERS = [
        '',
        'Hoofdverdichting',
    ];

    /**
     * @param list<list<string>> $rows
     */
    public static function grootboek(array $rows): string
    {
        return self::create(self::GROOTBOEK_HEADERS, $rows);
    }

    /**
     * @param list<list<string>> $rows
     */
    public static function verdichtingen(array $rows): string
    {
        return self::create(self::VERDICHTING_HEADERS, $rows);
    }

    /**
     * @param list<list<string>> $rows
     */
    public static function hoofdverdichtingen(array $rows): string
    {
        return self::create(self::HOOFDVERDICHTING_HEADERS, $rows);
    }

    /**
     * A synthetic version of the real exports: 31 groups and 197 accounts.
     *
     * @return array{grootboek: string, verdichtingen: string, hoofdverdichtingen: string, groups: int, accounts: int}
     */
    public static function largeDataset(): array
    {
        $parents = [];

        foreach (range(1, 6) as $parentNumber) {
            $parents[] = [(string) $parentNumber, 'Hoofdgroep ' . $parentNumber];
        }

        $verdichtingen = [];
        $accounts = [];
        $accountNumber = 1;

        foreach (range(0, 24) as $index) {
            $verdichtingNumber = (string) (10 + $index);
            $parentNumber = (string) (($index % 6) + 1);
            $parentName = 'Hoofdgroep ' . $parentNumber;
            $verdichtingName = 'Verdichting ' . $verdichtingNumber;

            $verdichtingen[] = [$verdichtingNumber, $verdichtingName, $parentNumber, $parentName];

            $accountsForGroup = $index < 22 ? 8 : 7;

            foreach (range(1, $accountsForGroup) as $_) {
                $accounts[] = [
                    (string) $accountNumber,
                    'Rekening ' . $accountNumber,
                    'W',
                    'O',
                    'C',
                    '',
                    $verdichtingNumber,
                    $verdichtingName,
                ];

                $accountNumber++;
            }
        }

        return [
            'grootboek' => self::grootboek($accounts),
            'verdichtingen' => self::verdichtingen($verdichtingen),
            'hoofdverdichtingen' => self::hoofdverdichtingen($parents),
            'groups' => count($parents) + count($verdichtingen),
            'accounts' => count($accounts),
        ];
    }

    /**
     * @return array{grootboek: string, verdichtingen: string, hoofdverdichtingen: string}
     */
    public static function smallDataset(): array
    {
        return [
            'hoofdverdichtingen' => self::hoofdverdichtingen([
                ['20', 'Kosten'],
                ['21', 'Opbrengsten'],
            ]),
            'verdichtingen' => self::verdichtingen([
                ['10', 'materiaal',   '20', 'Kosten'],
                ['11', 'lessen',      '20', 'Kosten'],
                ['12', 'contributie', '21', 'Opbrengsten'],
            ]),
            'grootboek' => self::grootboek([
                ['100', 'Materiaal',   'B', 'K', 'D', '', '10', 'materiaal'],
                ['200', 'Lessen',      'W', 'O', 'C', '', '11', 'lessen'],
                ['300', 'Contributie', 'W', 'O', 'C', '', '12', 'contributie'],
            ]),
        ];
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private static function create(array $headers, array $rows): string
    {
        $path = sys_get_temp_dir() . '/cost-center-import-' . bin2hex(random_bytes(8)) . '.csv';

        $lines = [self::line($headers)];

        foreach ($rows as $row) {
            $lines[] = self::line($row);
        }

        $contents = mb_convert_encoding(implode("\n", $lines), 'ISO-8859-1', 'UTF-8');

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write %s', $path));
        }

        return $path;
    }

    /** @param list<string> $values */
    private static function line(array $values): string
    {
        return implode(';', array_map(
            static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"',
            $values,
        ));
    }
}
