<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Infrastructure\Members\Import\CreatedMember;
use App\Infrastructure\Members\Import\MappedMemberRow;
use App\Infrastructure\Members\Import\MemberImportReportBuilder;
use DateTimeImmutable;
use Override;
use Tests\UnitTestCase;

final class MemberImportReportBuilderTest extends UnitTestCase
{
    private MemberImportReportBuilder $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new MemberImportReportBuilder();
    }

    public function test_build_counts_the_imported_and_stopped_members(): void
    {
        $anna = $this->createdMember();
        $bob = $this->createdMember(lidnr: '1002', name: 'Bob Bakker');
        $dana = $this->createdMember(
            lidnr: '1003',
            name: 'Dana Doekes',
            stopDate: new DateTimeImmutable('2026-12-31'),
        );

        $report = $this->subject->build(
            allMembers: [$anna, $bob, $dana],
            continuingMembers: [$anna, $bob],
            householdsCreated: 1,
            billingInstancesCreated: 7,
            erelidMembershipCreated: true,
        );

        static::assertSame(2, $report->importedMembers);
        static::assertSame(1, $report->stoppedMembers);
        static::assertSame(1, $report->householdsCreated);
        static::assertSame(3, $report->mandatesCreated);
        static::assertSame(7, $report->billingInstancesCreated);
        static::assertTrue($report->erelidMembershipCreated);
    }

    public function test_build_reports_leavers(): void
    {
        $anna = $this->createdMember();
        $bob = $this->createdMember(
            lidnr: '1002',
            name: 'Bob Bakker',
            email: 'bob@example.com',
            stopDate: new DateTimeImmutable('2026-12-31'),
        );

        $report = $this->subject->build(
            allMembers: [$anna, $bob],
            continuingMembers: [$anna],
            householdsCreated: 0,
            billingInstancesCreated: 1,
            erelidMembershipCreated: false,
        );

        static::assertCount(1, $report->leavers);
        static::assertSame('1002', $report->leavers[0]->lidnr);
        static::assertSame('Bob Bakker', $report->leavers[0]->name);
        static::assertSame('bob@example.com', $report->leavers[0]->email);
    }

    public function test_build_reports_rows_without_a_membership_type(): void
    {
        $frank = $this->createdMember(
            lidnr: '124361',
            name: 'Frank Frits',
            membershipType: null,
        );

        $report = $this->subject->build(
            allMembers: [$frank],
            continuingMembers: [$frank],
            householdsCreated: 0,
            billingInstancesCreated: 1,
            erelidMembershipCreated: false,
        );

        static::assertCount(1, $report->rowsWithoutMembershipType);
        static::assertSame('124361', $report->rowsWithoutMembershipType[0]->lidnr);
    }

    public function test_build_reports_email_mismatches_case_insensitively(): void
    {
        $mismatch = $this->createdMember(
            email: 'anna@example.com',
            contactEmail: 'other@example.com',
        );
        $caseOnly = $this->createdMember(
            lidnr: '1002',
            name: 'Bob Bakker',
            email: 'bob@example.com',
            contactEmail: 'BOB@EXAMPLE.COM',
        );
        $withoutContactEmail = $this->createdMember(
            lidnr: '1003',
            name: 'Chris Cornelissen',
            email: 'chris@example.com',
            contactEmail: null,
        );

        $report = $this->subject->build(
            allMembers: [$mismatch, $caseOnly, $withoutContactEmail],
            continuingMembers: [$mismatch, $caseOnly, $withoutContactEmail],
            householdsCreated: 0,
            billingInstancesCreated: 3,
            erelidMembershipCreated: false,
        );

        static::assertCount(1, $report->emailMismatches);
        static::assertSame('1001', $report->emailMismatches[0]->lidnr);
        static::assertSame('Anna Aalbers', $report->emailMismatches[0]->name);
        static::assertSame('anna@example.com', $report->emailMismatches[0]->email);
    }

    public function test_build_returns_empty_collections_when_there_are_no_members(): void
    {
        $report = $this->subject->build(
            allMembers: [],
            continuingMembers: [],
            householdsCreated: 0,
            billingInstancesCreated: 0,
            erelidMembershipCreated: false,
        );

        static::assertSame(0, $report->importedMembers);
        static::assertSame(0, $report->stoppedMembers);
        static::assertSame(0, $report->mandatesCreated);
        static::assertSame([], $report->leavers);
        static::assertSame([], $report->rowsWithoutMembershipType);
        static::assertSame([], $report->emailMismatches);
    }

    private function createdMember(
        string $lidnr = '1001',
        string $name = 'Anna Aalbers',
        string $email = 'anna@example.com',
        ?string $contactEmail = 'anna@example.com',
        ?string $membershipType = 'Volwassenlid',
        ?DateTimeImmutable $stopDate = null,
    ): CreatedMember {
        return new CreatedMember(
            MemberId::create(1),
            MembershipId::create(1),
            new MappedMemberRow(
                lidnr: $lidnr,
                firstName: 'Anna',
                infixName: '',
                lastName: 'Aalbers',
                fullName: $name,
                email: $email,
                contactEmail: $contactEmail,
                birthdate: new DateTimeImmutable('1990-01-01'),
                gender: Gender::Female,
                addressStreet: 'Dorpsstraat',
                addressHousenumber: '1',
                addressHousenumberAddition: null,
                addressPostalcode: '1000AA',
                addressCity: 'Almere',
                membershipType: $membershipType,
                membershipName: 'Windsurfer',
                isVolunteer: false,
                startDate: new DateTimeImmutable('2020-01-01'),
                stopDate: $stopDate,
                iban: 'NL10RABO0335556922',
                bic: 'RABONL2U',
                accountHolderName: $name,
            ),
        );
    }
}
