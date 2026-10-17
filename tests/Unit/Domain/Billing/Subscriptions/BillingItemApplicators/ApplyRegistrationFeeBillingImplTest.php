<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\BillableItems\BillableItemId;
use App\Domain\Billing\Subscriptions\BillableItemInstanceId;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyRegistrationFeeBillingImpl;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use Override;
use Tests\Unit\Domain\Billing\Subscriptions\BillableItemRepositoryExpectation;
use Tests\Unit\Domain\Members\ExtraMembershipBillingItemRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyRegistrationFeeBillingImplTest extends UnitTestCase
{
    private ExtraMembershipBillingItemRepositoryExpectation $extraMembershipBillingItemRepository;

    private BillableItemRepositoryExpectation $billableItemRepository;

    private ApplyRegistrationFeeBillingImpl $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->extraMembershipBillingItemRepository = ExtraMembershipBillingItemRepositoryExpectation::create();
        $this->billableItemRepository = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplyRegistrationFeeBillingImpl(
            $this->extraMembershipBillingItemRepository->mock,
            $this->billableItemRepository->mock,
        );
    }

    public function test_it_adds_the_registration_fee_instance(): void
    {
        $memberId = MemberId::create(1);
        $feeId = BillableItemId::create(30);

        $this->extraMembershipBillingItemRepository
            ->expectsGetByCode(ExtraMembershipItemCode::Inschrijfgeld, $feeId);
        $this->billableItemRepository
            ->expectsAdd($memberId, $feeId, null, BillableItemInstanceId::create(31));

        $this->subject->apply($memberId);
    }
}
