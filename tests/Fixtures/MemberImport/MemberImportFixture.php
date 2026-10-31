<?php

declare(strict_types=1);

namespace Tests\Fixtures\MemberImport;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class MemberImportFixture
{
    public const array HEADERS = [
        'Lidnr',
        'Soortlid',
        'Startdat. lid',
        'Stopdat. lid',
        'Voornaam',
        'Tussenv.',
        'Achternaam',
        'Naamvoluit',
        'Geb.datum',
        'Geslacht',
        'Straat',
        'Huisnr',
        'Huisnr.toev.',
        'Postcode',
        'Plaats',
        'E-mail privé',
        'Contact.email',
        'Afw. Tenaamst. incasso',
        'Banknummer 1',
        'BIC 1',
        'Deelname aan RTC',
        'Container stalling',
    ];

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string>|null $headers
     */
    public static function create(array $rows, string $sheetName = 'Worksheet', ?array $headers = null): string
    {
        $path = sys_get_temp_dir() . '/member-import-' . bin2hex(random_bytes(8)) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheetName);

        $writer->addRow(Row::fromValues(['Leden export']));
        $writer->addRow(Row::fromValues($headers ?? self::HEADERS));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(self::values($row)));
        }

        $writer->close();

        return $path;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function row(array $overrides = []): array
    {
        return [
            'Lidnr' => '1001',
            'Soortlid' => 'Volwassenlid',
            'Startdat. lid' => '2020-01-01',
            'Stopdat. lid' => '',
            'Voornaam' => 'Anna',
            'Tussenv.' => '',
            'Achternaam' => 'Aalbers',
            'Naamvoluit' => 'Anna Aalbers',
            'Geb.datum' => '1990-01-01',
            'Geslacht' => 'Vrouw',
            'Straat' => 'Dorpsstraat',
            'Huisnr' => '1',
            'Huisnr.toev.' => '',
            'Postcode' => '1000 AA',
            'Plaats' => 'Almere',
            'E-mail privé' => 'anna@example.com',
            'Contact.email' => 'anna@example.com',
            'Afw. Tenaamst. incasso' => '',
            'Banknummer 1' => 'NL10RABO0335556922',
            'BIC 1' => 'RABONL2U',
            'Deelname aan RTC' => 'Nee',
            'Container stalling' => 'Nee',
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<mixed>
     */
    private static function values(array $row): array
    {
        return array_map(
            static fn (string $header): mixed => $row[$header] ?? '',
            self::HEADERS,
        );
    }
}
