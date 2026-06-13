<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplySameHouseholdBillingImpl;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\HouseholdId;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use Override;
use Tests\Unit\Domain\Invoices\BillableItemRepositoryExpectation;
use Tests\Unit\Domain\Members\ExtraMembershipBillingItemRepositoryExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplySameHouseholdBillingImplTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepository;

    private ExtraMembershipBillingItemRepositoryExpectation $extraRepo;

    private BillableItemRepositoryExpectation $billableRepo;

    private ApplySameHouseholdBillingImpl $subject;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->memberRepository = MemberRepositoryExpectation::create();
        $this->extraRepo = ExtraMembershipBillingItemRepositoryExpectation::create();
        $this->billableRepo = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplySameHouseholdBillingImpl(
            $this->extraRepo->mock,
            $this->billableRepo->mock,
            $this->memberRepository->mock,
        );
    }

    public function test_member_in_household_youngster(): void
    {
        $memberId = MemberId::create(1);
        $youngsterId = BillableItemId::create(10);
        $adultId = BillableItemId::create(20);

        $this->memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(2), false, HouseholdId::create(5), 15),
        );

        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster, $youngsterId);
        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult, $adultId);
        $this->billableRepo->expectsRemove($memberId, new BillableItemIdList([$youngsterId, $adultId]));
        $this->billableRepo->expectsAdd($memberId, $youngsterId, null, BillableItemInstanceId::create(99));

        $this->subject->apply($memberId);
    }

    public function test_member_in_household_adult(): void
    {
        $memberId = MemberId::create(2);
        $youngsterId = BillableItemId::create(11);
        $adultId = BillableItemId::create(21);

        $this->memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(3), false, HouseholdId::create(6), 30),
        );

        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster, $youngsterId);
        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult, $adultId);
        $this->billableRepo->expectsRemove($memberId, new BillableItemIdList([$youngsterId, $adultId]));
        $this->billableRepo->expectsAdd($memberId, $adultId, null, BillableItemInstanceId::create(100));

        $this->subject->apply($memberId);
    }

    public function test_member_not_in_household(): void
    {
        $memberId = MemberId::create(3);
        $youngsterId = BillableItemId::create(12);
        $adultId = BillableItemId::create(22);

        $this->memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(4), false, null, 25),
        );

        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster, $youngsterId);
        $this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult, $adultId);
        $this->billableRepo->expectsRemove($memberId, new BillableItemIdList([$youngsterId, $adultId]));

        $this->subject->apply($memberId);
    }
}
