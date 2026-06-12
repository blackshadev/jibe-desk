<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\Dto\NewMemberPaymentInformation;
use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Members\Gender;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Infrastructure\Members\MemberDbRepository;
use App\Models\Member;
use App\Models\Membership;
use Database\Seeders\MembershipSeeder;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class MemberDbRepositoryTest extends FeatureTestCase
{
    public function test_get_by_id_returns_member_domain_object(): void
    {
        $model = Member::factory()->createQuietly(['is_volunteer' => true]);

        $repo = new MemberDbRepository();

        $domain = $repo->getById(MemberId::create($model->id));

        self::assertSame($model->id, $domain->id->value);
        self::assertSame($model->membership_id, $domain->membershipId->value);
        self::assertTrue($domain->isVolunteer);
    }

    public function test_get_by_id_throws_when_member_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $repo = new MemberDbRepository();

        $repo->getById(MemberId::create(999999));
    }

    public function test_new_member_creates_member_and_payment_information(): void
    {
        $this->seed(MembershipSeeder::class);

        $membership = Membership::query()->first();

        $newMember = new NewMember(
            new NewMemberMembershipInformation(MembershipId::create($membership->id)),
            new NewMemberPersonalInformation(
                firstName: 'Jan',
                infixName: 'de',
                lastName: 'Vries',
                gender: Gender::Male,
                birthdate: new DateTimeImmutable('1990-01-15'),
                email: 'jan@example.com',
                addressStreet: 'Surfstrand',
                addressHousenumber: '2',
                addressHousenumberAddition: 'A',
                addressPostalcode: '1324CT',
                addressCity: 'Almere',
            ),
            new NewMemberPaymentInformation(
                iban: 'NL91ABNA0417164300',
                bic: 'ABNANL2A',
                accountHolderName: 'J. de Vries',
                mandateAcceptedDate: new DateTimeImmutable('2024-02-01'),
            ),
            registrationData: ['windsurfing_lessons' => true],
        );

        $repo = new MemberDbRepository();

        $memberId = $repo->newMember($newMember);

        /** @var Member $member */
        $member = Member::findOrFail($memberId->value);

        self::assertSame('Jan', $member->first_name);
        self::assertSame('de', $member->infix_name);
        self::assertSame('Vries', $member->last_name);
        self::assertSame('jan@example.com', $member->email);
        self::assertSame(Gender::Male, $member->gender);
        self::assertSame($membership->id, $member->membership_id);
        self::assertFalse($member->is_volunteer);
        self::assertSame(['windsurfing_lessons' => true], $member->registration_data);

        $paymentInfo = $member->paymentInformation;
        self::assertNotNull($paymentInfo);
        self::assertSame('NL91ABNA0417164300', $paymentInfo->banking_account_number);
        self::assertSame('ABNANL2A', $paymentInfo->banking_bic);
        self::assertSame('J. de Vries', $paymentInfo->banking_account_holder_name);
        self::assertSame('2024-02-01', $paymentInfo->mandate_accepted_date->format('Y-m-d'));
    }

    public function test_new_member_returns_valid_member_id(): void
    {
        $this->seed(MembershipSeeder::class);

        $membership = Membership::query()->first();

        $newMember = new NewMember(
            new NewMemberMembershipInformation(MembershipId::create($membership->id)),
            new NewMemberPersonalInformation(
                firstName: 'Jan',
                infixName: '',
                lastName: 'Vries',
                gender: Gender::Male,
                birthdate: new DateTimeImmutable('1990-01-15'),
                email: 'jan@example.com',
                addressStreet: 'Surfstrand',
                addressHousenumber: '2',
                addressHousenumberAddition: '',
                addressPostalcode: '1324CT',
                addressCity: 'Almere',
            ),
            new NewMemberPaymentInformation(
                iban: 'NL91ABNA0417164300',
                bic: 'ABNANL2A',
                accountHolderName: 'J. de Vries',
                mandateAcceptedDate: new DateTimeImmutable('2024-02-01'),
            ),
        );

        $repo = new MemberDbRepository();

        $memberId = $repo->newMember($newMember);

        self::assertDatabaseHas('members', ['id' => $memberId->value]);
    }
}
