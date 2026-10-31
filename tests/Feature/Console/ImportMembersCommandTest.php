<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\Activity;
use App\Models\BillableItemInstance;
use App\Models\ExtraMembershipItem;
use App\Models\Household;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PaymentInformation;
use App\Models\StorageSpaceRental;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MembershipSeeder;
use Override;
use Tests\FeatureTestCase;
use Tests\Fixtures\MemberImport\MemberImportFixture;

final class ImportMembersCommandTest extends FeatureTestCase
{
    private ?string $spreadsheetPath = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->spreadsheetPath !== null && is_file($this->spreadsheetPath)) {
            unlink($this->spreadsheetPath);
        }

        parent::tearDown();
    }

    public function test_it_imports_members_households_mandates_and_billing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2027-06-01'));
        $path = $this->createSpreadsheet($this->rows());

        $this
            ->artisan('app:import-members', ['path' => $path])
            ->expectsOutputToContain('Import complete.')
            ->expectsOutputToContain('Leavers (1)')
            ->expectsOutputToContain('Rows defaulted to Windsurfer (1)')
            ->expectsOutputToContain('Email mismatches (1)')
            ->expectsOutputToContain('Dana Doekes')
            ->expectsOutputToContain('Gijs Groot')
            ->assertSuccessful();

        static::assertSame(8, Member::count());
        static::assertSame(7, Member::query()->active()->count());
        static::assertSame(1, Member::query()->stopped()->count());

        foreach (Member::all() as $member) {
            static::assertMatchesRegularExpression('/^\d{4}[A-Z]{2}$/', $member->address_postalcode);
        }

        $leaver = Member::query()->where('email', 'dana@example.com')->firstOrFail();
        static::assertSame('2026-12-31', $leaver->stopped_at?->format('Y-m-d'));
        static::assertSame('2015-05-05', $leaver->accepted_at?->format('Y-m-d'));

        $leaverMandate = PaymentInformation::query()->where('member_id', $leaver->id)->firstOrFail();
        static::assertSame('NL10RABO0335556922', $leaverMandate->banking_account_number);
        static::assertSame('2026-12-31', $leaverMandate->last_collected_at?->format('Y-m-d'));
        static::assertNull($leaverMandate->revoked_at);

        static::assertSame(1, Household::count());
        static::assertSame(2, Member::query()->whereNotNull('household_id')->count());
        static::assertSame(2, Household::query()->firstOrFail()->members()->count());

        static::assertSame(8, PaymentInformation::count());

        foreach (PaymentInformation::all() as $mandate) {
            static::assertSame('2026-12-31', $mandate->last_collected_at?->format('Y-m-d'));
            static::assertNull($mandate->revoked_at);
        }

        static::assertSame(18, BillableItemInstance::count());

        foreach (BillableItemInstance::all() as $instance) {
            static::assertSame('2027-01-01', $instance->start_date->format('Y-m-d'));
        }

        $registrationFee = ExtraMembershipItem::query()
            ->where('code', ExtraMembershipItemCode::Inschrijfgeld)
            ->firstOrFail();
        static::assertSame(
            0,
            BillableItemInstance::query()->where('billable_item_id', $registrationFee->billable_item_id)->count(),
        );

        $youngster = Member::query()->where('email', 'chris@example.com')->firstOrFail();
        $windsurfer = Membership::query()->where('name', 'Windsurfer')->firstOrFail();
        $adult = Member::query()->where('email', 'anna@example.com')->firstOrFail();
        static::assertSame('2020-01-01', $adult->accepted_at?->format('Y-m-d'));
        static::assertTrue(
            BillableItemInstance::query()
                ->where('member_id', $youngster->id)
                ->where('billable_item_id', $windsurfer->kids_billable_item_id)
                ->exists(),
        );

        static::assertSame(0, User::count());
        static::assertNull($youngster->user_id);
        static::assertNull($youngster->registration_data);
        static::assertSame(0, Activity::count());
        static::assertSame(0, StorageSpaceRental::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $path = $this->createSpreadsheet($this->rows());

        $this
            ->artisan('app:import-members', ['path' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Dry run complete. The transaction was rolled back and no data was written.')
            ->assertSuccessful();

        static::assertSame(0, Member::count());
        static::assertSame(0, PaymentInformation::count());
        static::assertSame(0, Household::count());
        static::assertSame(0, BillableItemInstance::count());
    }

    public function test_it_aborts_when_the_members_table_is_not_empty(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        Member::factory()->createQuietly();
        $path = $this->createSpreadsheet($this->rows());

        $this
            ->artisan('app:import-members', ['path' => $path])
            ->expectsOutputToContain('The members table is not empty.')
            ->assertFailed();

        static::assertSame(1, Member::count());
    }

    public function test_a_row_error_rolls_back_the_whole_import(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $path = $this->createSpreadsheet([
            MemberImportFixture::row(),
            MemberImportFixture::row([
                'Lidnr' => '1002',
                'Voornaam' => 'Broken',
                'Achternaam' => 'Row',
                'E-mail privé' => 'broken@example.com',
                'Banknummer 1' => 'NL00BANK0123456789',
            ]),
        ]);

        $this
            ->artisan('app:import-members', ['path' => $path])
            ->expectsOutputToContain('Row 4, column "Banknummer 1"')
            ->assertFailed();

        static::assertSame(0, Member::count());
        static::assertSame(0, PaymentInformation::count());
        static::assertSame(0, BillableItemInstance::count());
    }

    public function test_it_rejects_an_impossibly_young_instructor(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        $path = $this->createSpreadsheet([
            MemberImportFixture::row(),
            MemberImportFixture::row([
                'Lidnr' => '124352',
                'Soortlid' => 'zeil-instructeur - 2026',
                'Voornaam' => 'Sander',
                'Achternaam' => 'Hofsink',
                'E-mail privé' => 'sander@example.com',
                'Geb.datum' => '2026-04-25',
            ]),
        ]);

        $this
            ->artisan('app:import-members', ['path' => $path])
            ->expectsOutputToContain('Row 4, column "Geb.datum", value "2026-04-25" is too young to be a sailing instructor.')
            ->assertFailed();

        static::assertSame(0, Member::count());
    }

    public function test_it_creates_the_erelid_membership_when_it_is_missing(): void
    {
        $this->seed([CostCenterSeeder::class, MembershipSeeder::class]);
        Membership::query()->where('name', 'Erelid')->delete();
        $path = $this->createSpreadsheet([
            MemberImportFixture::row(['Lidnr' => '124175', 'Soortlid' => 'Erelid']),
        ]);

        $this->artisan('app:import-members', ['path' => $path])->assertSuccessful();

        $erelid = Membership::query()->where('name', 'Erelid')->firstOrFail();

        static::assertSame('0.00', $erelid->adultBillableItem->price);
        static::assertSame('0.00', $erelid->kidsBillableItem->price);
        static::assertSame(1, Member::query()->where('membership_id', $erelid->id)->count());
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return [
            $this->annaRow(),
            $this->bobRow(),
            $this->chrisRow(),
            $this->danaRow(),
            $this->evaRow(),
            $this->frankRow(),
            $this->gijsRow(),
            $this->hansRow(),
        ];
    }

    /** @return array<string, mixed> */
    private function annaRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1001',
            'Soortlid' => 'commissielid',
            'Voornaam' => 'Anna',
            'Achternaam' => 'Aalbers',
            'Naamvoluit' => 'Anna Aalbers',
            'E-mail privé' => 'anna@example.com',
            'Contact.email' => 'anna@example.com',
        ]);
    }

    /** @return array<string, mixed> */
    private function bobRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1002',
            'Soortlid' => 'bestuurslid',
            'Voornaam' => 'Bob',
            'Achternaam' => 'Bakker',
            'Naamvoluit' => 'Bob Bakker',
            'Geb.datum' => '1985-02-02',
            'E-mail privé' => 'bob@example.com',
            'Contact.email' => 'bob@example.com',
            'Banknummer 1' => 'NL75ABNA0510419224',
            'BIC 1' => 'ABNANL2A',
        ]);
    }

    /** @return array<string, mixed> */
    private function chrisRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1003',
            'Soortlid' => 'Jeugdlid',
            'Voornaam' => 'Chris',
            'Achternaam' => 'Cornelissen',
            'Naamvoluit' => 'Chris Cornelissen',
            'Geb.datum' => '2015-03-03',
            'E-mail privé' => 'chris@example.com',
            'Contact.email' => 'chris@example.com',
            'Straat' => 'Parklaan',
            'Huisnr' => '2',
            'Postcode' => '2000 BB',
            'Banknummer 1' => 'NL43RABO0104405813',
        ]);
    }

    /** @return array<string, mixed> */
    private function danaRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1004',
            'Soortlid' => 'Gezinslid volwassen',
            'Voornaam' => 'Dana',
            'Achternaam' => 'Doekes',
            'Naamvoluit' => 'Dana Doekes',
            'Geb.datum' => '1980-04-04',
            'E-mail privé' => 'dana@example.com',
            'Contact.email' => 'dana@example.com',
            'Straat' => 'Laan',
            'Huisnr' => '3',
            'Postcode' => '3000 CC',
            'Startdat. lid' => '2015-05-05',
            'Stopdat. lid' => '2026-12-31',
        ]);
    }

    /** @return array<string, mixed> */
    private function evaRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1005',
            'Soortlid' => 'Gezinslid jeugd',
            'Voornaam' => 'Eva',
            'Achternaam' => 'Doekes',
            'Naamvoluit' => 'Eva Doekes',
            'Geb.datum' => '2012-05-05',
            'E-mail privé' => 'eva@example.com',
            'Contact.email' => 'eva@example.com',
            'Straat' => 'Laan',
            'Huisnr' => '3',
            'Postcode' => '3000 CC',
            'Banknummer 1' => 'NL75ABNA0510419224',
        ]);
    }

    /** @return array<string, mixed> */
    private function frankRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '124361',
            'Soortlid' => '',
            'Voornaam' => 'Frank',
            'Achternaam' => 'Frits',
            'Naamvoluit' => 'Frank Frits',
            'Geb.datum' => '1975-06-06',
            'E-mail privé' => 'frank@example.com',
            'Contact.email' => 'frank@example.com',
            'Straat' => 'Singel',
            'Huisnr' => '4',
            'Postcode' => '4000 DD',
        ]);
    }

    /** @return array<string, mixed> */
    private function gijsRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '124175',
            'Soortlid' => 'zeil-instructeur - 2026',
            'Voornaam' => 'Gijs',
            'Achternaam' => 'Groot',
            'Naamvoluit' => 'Gijs Groot',
            'Geb.datum' => '1985-07-07',
            'E-mail privé' => 'jimmyfock@hotmail.com',
            'Contact.email' => 'Kickiefock@icloud.com',
            'Straat' => 'Dijk',
            'Huisnr' => '5',
            'Postcode' => '5000 EE',
            'Banknummer 1' => 'NL43RABO0104405813',
        ]);
    }

    /** @return array<string, mixed> */
    private function hansRow(): array
    {
        return MemberImportFixture::row([
            'Lidnr' => '1008',
            'Soortlid' => 'Erelid',
            'Voornaam' => 'Hans',
            'Achternaam' => 'Hendriks',
            'Naamvoluit' => 'Hans Hendriks',
            'Geb.datum' => '1950-08-08',
            'E-mail privé' => 'hans@example.com',
            'Contact.email' => 'hans@example.com',
            'Straat' => 'Plein',
            'Huisnr' => '6',
            'Postcode' => '6000 FF',
        ]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function createSpreadsheet(array $rows): string
    {
        $this->spreadsheetPath = MemberImportFixture::create($rows);

        return $this->spreadsheetPath;
    }
}
