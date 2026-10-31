<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Infrastructure\Members\Import\CreatedMember;
use App\Infrastructure\Members\Import\HouseholdCreator;
use App\Infrastructure\Members\Import\MappedMemberRow;
use App\Models\Household;
use App\Models\Member;
use DateTimeImmutable;
use Override;
use Tests\FeatureTestCase;

final class HouseholdCreatorTest extends FeatureTestCase
{
    private HouseholdCreator $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new HouseholdCreator();
    }

    public function test_create_groups_members_at_the_same_address_into_one_household(): void
    {
        $anna = $this->member();
        $bob = $this->member();
        $chris = $this->member();

        $created = $this->subject->create([
            $this->createdMember($anna, 'Dorpsstraat', '1'),
            $this->createdMember($bob, 'Dorpsstraat', '1'),
            $this->createdMember($chris, 'Parklaan', '2'),
        ]);

        static::assertSame(1, $created);
        static::assertSame(1, Household::count());

        $anna->refresh();
        $bob->refresh();
        $chris->refresh();

        static::assertNotNull($anna->household_id);
        static::assertSame($anna->household_id, $bob->household_id);
        static::assertNull($chris->household_id);
    }

    public function test_create_makes_a_household_for_each_shared_address(): void
    {
        $anna = $this->member();
        $bob = $this->member();
        $chris = $this->member();
        $dana = $this->member();

        $created = $this->subject->create([
            $this->createdMember($anna, 'Dorpsstraat', '1'),
            $this->createdMember($bob, 'Dorpsstraat', '1'),
            $this->createdMember($chris, 'Parklaan', '2'),
            $this->createdMember($dana, 'Parklaan', '2'),
        ]);

        static::assertSame(2, $created);
        static::assertSame(2, Household::count());
        static::assertNotSame($anna->refresh()->household_id, $chris->refresh()->household_id);
    }

    public function test_create_does_not_make_a_household_for_a_member_living_alone(): void
    {
        $anna = $this->member();

        $created = $this->subject->create([$this->createdMember($anna, 'Dorpsstraat', '1')]);

        static::assertSame(0, $created);
        static::assertSame(0, Household::count());
        static::assertNull($anna->refresh()->household_id);
    }

    public function test_create_ignores_leavers_living_at_a_shared_address(): void
    {
        $anna = $this->member();
        $dana = $this->member();

        $created = $this->subject->create([
            $this->createdMember($anna, 'Dorpsstraat', '1'),
            $this->createdMember($dana, 'Dorpsstraat', '1', stopDate: new DateTimeImmutable('2026-12-31')),
        ]);

        static::assertSame(0, $created);
        static::assertSame(0, Household::count());
        static::assertNull($anna->refresh()->household_id);
        static::assertNull($dana->refresh()->household_id);
    }

    public function test_create_groups_addresses_case_insensitively(): void
    {
        $anna = $this->member();
        $bob = $this->member();

        $created = $this->subject->create([
            $this->createdMember($anna, 'Dorpsstraat', '1', addition: 'A'),
            $this->createdMember($bob, 'dorpsstraat', '1', addition: 'a'),
        ]);

        static::assertSame(1, $created);
        static::assertSame($anna->refresh()->household_id, $bob->refresh()->household_id);
    }

    private function member(): Member
    {
        return Member::factory()->createQuietly();
    }

    private function createdMember(
        Member $member,
        string $street,
        string $housenumber,
        ?string $addition = null,
        ?DateTimeImmutable $stopDate = null,
    ): CreatedMember {
        return new CreatedMember(
            MemberId::create($member->id),
            MembershipId::create((int) $member->membership_id),
            new MappedMemberRow(
                lidnr: (string) $member->id,
                firstName: $member->first_name,
                infixName: $member->infix_name ?? '',
                lastName: $member->last_name,
                fullName: $member->name,
                email: $member->email,
                contactEmail: $member->email,
                birthdate: new DateTimeImmutable('1990-01-01'),
                gender: Gender::Female,
                addressStreet: $street,
                addressHousenumber: $housenumber,
                addressHousenumberAddition: $addition,
                addressPostalcode: '1000 AA',
                addressCity: 'Almere',
                membershipType: 'Volwassenlid',
                membershipName: 'Windsurfer',
                isVolunteer: false,
                startDate: new DateTimeImmutable('2020-01-01'),
                stopDate: $stopDate,
                iban: 'NL10RABO0335556922',
                bic: 'RABONL2U',
                accountHolderName: $member->name,
                attendsRtc: false,
                hasContainer: false,
            ),
        );
    }
}
