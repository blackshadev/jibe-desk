<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use App\Infrastructure\Members\Import\MemberSpreadsheetReader;
use App\Infrastructure\Members\Import\MemberSpreadsheetValidator;
use App\Infrastructure\Members\Import\SpreadsheetRowMapper;
use Override;
use Tests\Fixtures\MemberImport\MemberImportFixture;
use Tests\UnitTestCase;

final class MemberSpreadsheetValidatorTest extends UnitTestCase
{
    /** @var list<string> */
    private array $spreadsheetPaths = [];

    private MemberSpreadsheetValidator $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new MemberSpreadsheetValidator(
            new MemberSpreadsheetReader(),
            new SpreadsheetRowMapper(),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->spreadsheetPaths as $path) {
            if (!is_file($path)) {
                continue;
            }

            unlink($path);
        }

        parent::tearDown();
    }

    public function test_validate_returns_the_mapped_rows(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row(),
            MemberImportFixture::row([
                'Lidnr' => '1002',
                'Voornaam' => 'Bob',
                'E-mail privé' => 'bob@example.com',
            ]),
        ]);

        $rows = $this->subject->validate($path);

        static::assertCount(2, $rows);
        static::assertSame('1001', $rows[0]->lidnr);
        static::assertSame('Anna', $rows[0]->firstName);
        static::assertSame('Aalbers', $rows[0]->lastName);
        static::assertSame('1002', $rows[1]->lidnr);
        static::assertSame('Bob', $rows[1]->firstName);
        static::assertSame('bob@example.com', $rows[1]->email);
    }

    public function test_validate_returns_an_empty_list_without_data_rows(): void
    {
        static::assertSame([], $this->subject->validate($this->spreadsheet([])));
    }

    public function test_validate_accepts_an_instructor_who_is_exactly_sixteen_at_the_start_date(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row([
                'Soortlid' => 'zeil-instructeur - 2026',
                'Geb.datum' => '2008-04-01',
                'Startdat. lid' => '2024-04-01',
            ]),
        ]);

        $rows = $this->subject->validate($path);

        static::assertCount(1, $rows);
        static::assertTrue($rows[0]->isSailInstructor());
    }

    public function test_validate_accepts_an_instructor_older_than_sixteen_at_the_start_date(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row([
                'Soortlid' => 'zeil-instructeur - 2026',
                'Geb.datum' => '2003-06-26',
                'Startdat. lid' => '2024-04-01',
            ]),
        ]);

        $rows = $this->subject->validate($path);

        static::assertCount(1, $rows);
        static::assertSame('2003-06-26', $rows[0]->birthdate->format('Y-m-d'));
    }

    public function test_validate_rejects_an_instructor_younger_than_sixteen_at_the_start_date(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row([
                'Soortlid' => 'zeil-instructeur - 2026',
                'Geb.datum' => '2008-04-02',
                'Startdat. lid' => '2024-04-01',
            ]),
        ]);

        try {
            $this->subject->validate($path);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(3, $exception->rowNumber);
            static::assertSame('Geb.datum', $exception->column);
            static::assertSame('2008-04-02', $exception->value);
            static::assertSame(
                'Row 3, column "Geb.datum", value "2008-04-02" is too young to be a sailing instructor.',
                $exception->getMessage(),
            );
        }
    }

    public function test_validate_reports_the_spreadsheet_row_number_of_the_offending_instructor(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row(),
            MemberImportFixture::row([
                'Lidnr' => '1002',
                'Soortlid' => 'zeil-instructeur - 2026',
                'Geb.datum' => '2015-01-01',
                'Startdat. lid' => '2024-01-01',
            ]),
        ]);

        try {
            $this->subject->validate($path);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(4, $exception->rowNumber);
        }
    }

    public function test_validate_does_not_apply_the_age_limit_to_other_membership_types(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row([
                'Soortlid' => 'Jeugdlid',
                'Geb.datum' => '2018-01-01',
                'Startdat. lid' => '2024-01-01',
            ]),
        ]);

        $rows = $this->subject->validate($path);

        static::assertCount(1, $rows);
        static::assertSame('Jeugdlid', $rows[0]->membershipType);
        static::assertFalse($rows[0]->isSailInstructor());
    }

    public function test_validate_does_not_apply_the_age_limit_without_a_membership_type(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row([
                'Soortlid' => '',
                'Geb.datum' => '2018-01-01',
                'Startdat. lid' => '2024-01-01',
            ]),
        ]);

        $rows = $this->subject->validate($path);

        static::assertCount(1, $rows);
        static::assertNull($rows[0]->membershipType);
    }

    public function test_validate_propagates_mapping_errors_with_the_row_number(): void
    {
        $path = $this->spreadsheet([
            MemberImportFixture::row(['Voornaam' => '']),
        ]);

        try {
            $this->subject->validate($path);
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(3, $exception->rowNumber);
            static::assertSame('Voornaam', $exception->column);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function spreadsheet(array $rows): string
    {
        $path = MemberImportFixture::create($rows);
        $this->spreadsheetPaths[] = $path;

        return $path;
    }
}
