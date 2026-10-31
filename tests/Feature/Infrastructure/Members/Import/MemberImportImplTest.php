<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use App\Domain\Members\Import\MemberImportRequest;
use App\Domain\Members\PaymentMethod;
use App\Infrastructure\Members\Import\MemberImportImpl;
use App\Models\BillableItemInstance;
use App\Models\Household;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MembershipSeeder;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\MemberImportFixture;

final class MemberImportImplTest extends FeatureTestCase
{
    private MemberImportImpl $subject;

    /** @var list<string> */
    private array $spreadsheetPaths = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = app(MemberImportImpl::class);
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

    public function test_import_creates_members_households_mandates_and_billing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2027-06-01'));

        $report = $this->subject->import($this->request([
            $this->annaRow(),
            $this->bobRow(),
            $this->chrisRow(),
            $this->danaRow(),
            $this->frankRow(),
            $this->gijsRow(),
        ]));

        static::assertSame(6, Member::count());
        static::assertSame(5, Member::query()->active()->count());
        static::assertSame(1, Member::query()->stopped()->count());

        static::assertSame(5, $report->importedMembers);
        static::assertSame(1, $report->stoppedMembers);
        static::assertSame(1, $report->householdsCreated);
        static::assertSame(6, $report->mandatesCreated);
        static::assertFalse($report->erelidMembershipCreated);

        static::assertCount(1, $report->leavers);
        static::assertSame('1004', $report->leavers[0]->lidnr);
        static::assertCount(1, $report->rowsWithoutMembershipType);
        static::assertSame('124361', $report->rowsWithoutMembershipType[0]->lidnr);
        static::assertCount(1, $report->emailMismatches);
        static::assertSame('124175', $report->emailMismatches[0]->lidnr);
    }

    public function test_import_maps_the_spreadsheet_values_onto_the_members(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        $this->subject->import($this->request([
            $this->annaRow(),
            $this->bobRow(),
            $this->chrisRow(),
            $this->danaRow(),
            $this->frankRow(),
            $this->gijsRow(),
        ]));

        $anna = Member::query()->where('email', 'anna@example.com')->firstOrFail();
        $bob = Member::query()->where('email', 'bob@example.com')->firstOrFail();
        $chris = Member::query()->where('email', 'chris@example.com')->firstOrFail();
        $dana = Member::query()->where('email', 'dana@example.com')->firstOrFail();
        $frank = Member::query()->where('email', 'frank@example.com')->firstOrFail();
        $gijs = Member::query()->where('email', 'jimmyfock@hotmail.com')->firstOrFail();

        static::assertSame(PaymentMethod::DirectDebit, $anna->payment_method);
        static::assertTrue($anna->is_volunteer);
        static::assertSame('2020-01-01', $anna->accepted_at?->format('Y-m-d'));
        static::assertNull($anna->stopped_at);

        static::assertSame('Bestuurslid', $bob->membership->name);
        static::assertTrue($bob->is_volunteer);
        static::assertSame('Windsurfer', $chris->membership->name);
        static::assertSame('2026-12-31', $dana->stopped_at?->format('Y-m-d'));
        static::assertSame('Windsurfer', $frank->membership->name);
        static::assertSame('Zeiler', $gijs->membership->name);
    }

    public function test_import_groups_continuing_members_into_households(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        $this->subject->import($this->request([
            $this->bobRow(),
            $this->chrisRow(),
            $this->danaRow(),
        ]));

        static::assertSame(1, Household::count());

        $household = Household::query()->firstOrFail();
        $bob = Member::query()->where('email', 'bob@example.com')->firstOrFail();
        $chris = Member::query()->where('email', 'chris@example.com')->firstOrFail();
        $dana = Member::query()->where('email', 'dana@example.com')->firstOrFail();

        static::assertSame($household->id, $bob->household_id);
        static::assertSame($household->id, $chris->household_id);
        static::assertNull($dana->household_id);
        static::assertSame(2, $household->members()->count());
    }

    public function test_import_creates_payment_information_for_every_member_and_billing_for_continuing_members(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        $report = $this->subject->import($this->request([
            $this->annaRow(),
            $this->bobRow(),
            $this->chrisRow(),
            $this->danaRow(),
        ]));

        static::assertSame(4, PaymentInformation::count());
        static::assertSame(4, $report->mandatesCreated);

        foreach (PaymentInformation::all() as $mandate) {
            static::assertSame('2026-12-31', $mandate->last_collected_at?->format('Y-m-d'));
            static::assertNull($mandate->revoked_at);
        }

        static::assertGreaterThan(0, $report->billingInstancesCreated);
        static::assertSame(
            $report->billingInstancesCreated,
            BillableItemInstance::query()->count(),
        );

        foreach (BillableItemInstance::all() as $instance) {
            static::assertSame('2027-01-01', $instance->start_date->format('Y-m-d'));
        }

        $leaver = Member::query()->where('email', 'dana@example.com')->firstOrFail();
        $leaverMandate = PaymentInformation::query()->where('member_id', $leaver->id)->firstOrFail();
        static::assertSame('NL10RABO0335556922', $leaverMandate->banking_account_number);
        static::assertSame('2026-12-31', $leaverMandate->last_collected_at?->format('Y-m-d'));
        static::assertNull($leaverMandate->revoked_at);
        static::assertFalse(
            BillableItemInstance::query()->where('member_id', $leaver->id)->exists(),
        );

        foreach (Member::query()->whereNull('stopped_at')->get() as $member) {
            static::assertTrue(
                BillableItemInstance::query()->where('member_id', $member->id)->exists(),
            );
        }
    }

    public function test_import_dry_run_reports_without_writing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $membershipCount = Membership::query()->count();

        $report = $this->subject->import($this->request([$this->annaRow()], dryRun: true));

        static::assertSame(1, $report->importedMembers);
        static::assertSame(1, $report->mandatesCreated);
        static::assertSame(0, Member::count());
        static::assertSame(0, Household::count());
        static::assertSame(0, PaymentInformation::count());
        static::assertSame(0, BillableItemInstance::count());
        static::assertSame($membershipCount, Membership::query()->count());
    }

    public function test_import_refuses_to_run_when_the_members_table_is_not_empty(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        Member::factory()->createQuietly();
        $path = $this->spreadsheet([$this->annaRow()]);

        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('The members table is not empty. This import only runs against a fresh database.');

        $this->subject->import($this->requestForPath($path));
    }

    public function test_import_rolls_back_everything_when_a_row_is_invalid(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        try {
            $this->subject->import($this->request([
                $this->annaRow(),
                $this->row([
                    'Lidnr' => '1002',
                    'Voornaam' => 'Broken',
                    'E-mail privé' => 'broken@example.com',
                    'Banknummer 1' => 'NL00BANK0123456789',
                ]),
            ]));
            static::fail('Expected exception');
        } catch (MemberImportException $exception) {
            static::assertSame(4, $exception->rowNumber);
            static::assertSame('Banknummer 1', $exception->column);
        }

        static::assertSame(0, Member::count());
        static::assertSame(0, Household::count());
        static::assertSame(0, PaymentInformation::count());
        static::assertSame(0, BillableItemInstance::count());
    }

    public function test_import_rejects_a_sail_instructor_under_sixteen(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Row 3, column "Geb.datum", value "2011-01-01" is too young to be a sailing instructor.');

        $this->subject->import($this->request([
            $this->row([
                'Lidnr' => '124175',
                'Soortlid' => 'zeil-instructeur - 2026',
                'E-mail privé' => 'sander@example.com',
                'Geb.datum' => '2011-01-01',
                'Startdat. lid' => '2026-01-01',
            ]),
        ]));
    }

    public function test_import_accepts_a_sail_instructor_of_sixteen(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);

        $this->subject->import($this->request([
            $this->row([
                'Lidnr' => '124175',
                'Soortlid' => 'zeil-instructeur - 2026',
                'E-mail privé' => 'sander@example.com',
                'Geb.datum' => '2010-01-01',
                'Startdat. lid' => '2026-01-01',
            ]),
        ]));

        static::assertSame(1, Member::query()->where('email', 'sander@example.com')->count());
    }

    public function test_import_creates_the_erelid_membership_when_it_is_missing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        Membership::query()->where('name', 'Erelid')->delete();

        $report = $this->subject->import($this->request([
            $this->row(['Lidnr' => '124175', 'Soortlid' => 'Erelid', 'E-mail privé' => 'erelid@example.com']),
        ]));

        static::assertTrue($report->erelidMembershipCreated);

        $erelid = Membership::query()->where('name', 'Erelid')->firstOrFail();

        static::assertSame('0.00', $erelid->adultBillableItem->price);
        static::assertSame('0.00', $erelid->kidsBillableItem->price);
        static::assertSame(1, Member::query()->where('membership_id', $erelid->id)->count());
    }

    public function test_import_throws_when_a_membership_tier_is_missing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        Membership::query()->where('name', 'Windsurfer')->delete();

        $this->expectException(MemberImportException::class);
        $this->expectExceptionMessage('Membership tier "Windsurfer" does not exist and could not be created.');

        $this->subject->import($this->request([$this->annaRow()]));
    }

    /** @param list<array<string, mixed>> $rows */
    private function request(array $rows, bool $dryRun = false): MemberImportRequest
    {
        return $this->requestForPath($this->spreadsheet($rows), $dryRun);
    }

    private function requestForPath(string $path, bool $dryRun = false): MemberImportRequest
    {
        return new MemberImportRequest(
            path: $path,
            dryRun: $dryRun,
            billingStart: new DateTimeImmutable('2027-01-01'),
            lastCollected: new DateTimeImmutable('2026-12-31'),
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function spreadsheet(array $rows): string
    {
        $path = MemberImportFixture::create($rows);
        $this->spreadsheetPaths[] = $path;

        return $path;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return MemberImportFixture::row($overrides);
    }

    /** @return array<string, mixed> */
    private function annaRow(): array
    {
        return $this->row([
            'Lidnr' => '1001',
            'Soortlid' => 'commissielid',
            'Voornaam' => 'Anna',
            'Naamvoluit' => 'Anna Aalbers',
            'E-mail privé' => 'anna@example.com',
            'Contact.email' => 'anna@example.com',
        ]);
    }

    /** @return array<string, mixed> */
    private function bobRow(): array
    {
        return $this->row([
            'Lidnr' => '1002',
            'Soortlid' => 'bestuurslid',
            'Voornaam' => 'Bob',
            'Naamvoluit' => 'Bob Bakker',
            'Geb.datum' => '1985-02-02',
            'E-mail privé' => 'bob@example.com',
            'Contact.email' => 'bob@example.com',
            'Straat' => 'Laan',
            'Huisnr' => '3',
        ]);
    }

    /** @return array<string, mixed> */
    private function chrisRow(): array
    {
        return $this->row([
            'Lidnr' => '1003',
            'Soortlid' => 'Jeugdlid',
            'Voornaam' => 'Chris',
            'Naamvoluit' => 'Chris Cornelissen',
            'Geb.datum' => '2015-03-03',
            'E-mail privé' => 'chris@example.com',
            'Contact.email' => 'chris@example.com',
            'Straat' => 'Laan',
            'Huisnr' => '3',
        ]);
    }

    /** @return array<string, mixed> */
    private function danaRow(): array
    {
        return $this->row([
            'Lidnr' => '1004',
            'Soortlid' => 'Gezinslid volwassen',
            'Voornaam' => 'Dana',
            'Naamvoluit' => 'Dana Doekes',
            'Geb.datum' => '1980-04-04',
            'E-mail privé' => 'dana@example.com',
            'Contact.email' => 'dana@example.com',
            'Straat' => 'Laan',
            'Huisnr' => '3',
            'Startdat. lid' => '2015-05-05',
            'Stopdat. lid' => '2026-12-31',
        ]);
    }

    /** @return array<string, mixed> */
    private function frankRow(): array
    {
        return $this->row([
            'Lidnr' => '124361',
            'Soortlid' => '',
            'Voornaam' => 'Frank',
            'Naamvoluit' => 'Frank Frits',
            'Geb.datum' => '1975-06-06',
            'E-mail privé' => 'frank@example.com',
            'Contact.email' => 'frank@example.com',
            'Straat' => 'Singel',
            'Huisnr' => '4',
        ]);
    }

    /** @return array<string, mixed> */
    private function gijsRow(): array
    {
        return $this->row([
            'Lidnr' => '124175',
            'Soortlid' => 'zeil-instructeur - 2026',
            'Voornaam' => 'Gijs',
            'Naamvoluit' => 'Gijs Groot',
            'Geb.datum' => '1985-07-07',
            'E-mail privé' => 'jimmyfock@hotmail.com',
            'Contact.email' => 'Kickiefock@icloud.com',
            'Straat' => 'Dijk',
            'Huisnr' => '5',
        ]);
    }
}
