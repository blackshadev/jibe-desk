<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\HouseholdId;
use App\Domain\Members\Member as MemberEntity;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MemberRepository;
use App\Domain\Members\MembershipId;
use App\Domain\Members\PaymentMethod;
use App\Models\Member;
use Override;

final class MemberDbRepository implements MemberRepository
{
    #[Override]
    public function getById(MemberId $memberId): MemberEntity
    {
        $model = Member::findOrFail($memberId->value);

        $householdId = $model->household_id;

        return new MemberEntity(
            id: MemberId::create($model->id),
            membershipId: MembershipId::create($model->membership_id),
            isVolunteer: $model->is_volunteer,
            householdId: $householdId ? HouseholdId::create($householdId) : null,
            age: $model->age,
            name: MemberNameFormatter::presentationName(
                $model->first_name,
                $model->infix_name,
                $model->last_name,
            ),
            email: $model->email,
            acceptedAt: $model->accepted_at,
        );
    }

    #[Override]
    public function newMember(NewMember $newMember): MemberId
    {
        /** @var Member $member */
        $member = Member::create([
            'first_name' => $newMember->personalInformation->firstName,
            'infix_name' => $newMember->personalInformation->infixName,
            'last_name' => $newMember->personalInformation->lastName,
            'email' => $newMember->personalInformation->email,
            'gender' => $newMember->personalInformation->gender,
            'birthdate' => $newMember->personalInformation->birthdate,
            'address_street' => $newMember->personalInformation->addressStreet,
            'address_housenumber' => $newMember->personalInformation->addressHousenumber,
            'address_housenumber_addition' => $newMember->personalInformation->addressHousenumberAddition,
            'address_postalcode' => $newMember->personalInformation->addressPostalcode,
            'address_city' => $newMember->personalInformation->addressCity,
            'is_volunteer' => false,
            'membership_id' => $newMember->membershipInformation->membershipId->value,
            'payment_method' => PaymentMethod::DirectDebit,
            'registration_data' => $newMember->registrationData,
            'accepted_at' => null,
        ]);

        $member
            ->paymentInformation()
            ->create([
                'banking_account_number' => $newMember->paymentInformation->iban,
                'banking_bic' => $newMember->paymentInformation->bic,
                'banking_account_holder_name' => $newMember->paymentInformation->accountHolderName,
                'mandate_accepted_date' => $newMember->paymentInformation->mandateAcceptedDate,
            ]);

        return MemberId::create($member->id);
    }

    #[Override]
    public function getByEmail(string $email): ?MemberId
    {
        $member = Member::query()
            ->where('email', $email)
            ->select('id')
            ->first();

        if ($member === null) {
            return null;
        }

        return MemberId::create($member->id);
    }

    #[Override]
    public function accept(MemberId $memberId): void
    {
        $member = Member::query()->findOrFail($memberId->value);

        if ($member->accepted_at !== null) {
            return;
        }

        $member->accepted_at = now();
        $member->save();
    }
}
