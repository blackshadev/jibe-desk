<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Members\MemberId;
use App\Infrastructure\Billing\Payment\MandateReadinessServiceImpl;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;

final class MandateReadinessServiceImplTest extends FeatureTestCase
{
    private MandateReadinessServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MandateReadinessServiceImpl::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_for_members_returns_an_active_mandate_with_iban_and_reference(): void
    {
        $member = Member::factory()->createQuietly();
        $paymentInformation = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertArrayHasKey($member->id, $result);
        static::assertSame(MandateReadinessStatus::Active, $result[$member->id]->status);
        static::assertSame('NL91ABNA0417164300', $result[$member->id]->iban);
        static::assertSame('ABNANL2A', $result[$member->id]->bic);
        static::assertSame(
            new MandateId(MemberId::create($member->id), PaymentInformationId::create($paymentInformation->id))->value,
            $result[$member->id]->mandateId?->value,
        );
        static::assertSame('2025-01-15', $result[$member->id]->signedOn?->format('Y-m-d'));
    }

    public function test_for_members_returns_missing_for_a_member_without_payment_information(): void
    {
        $member = Member::factory()->createQuietly();

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertArrayNotHasKey($member->id, $result);
    }

    public function test_for_members_returns_active_for_a_mandate_within_thirty_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->signedAt(CarbonImmutable::parse('2023-11-01'))->createQuietly([
            'member_id' => $member->id,
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertSame(MandateReadinessStatus::Active, $result[$member->id]->status);
    }

    public function test_for_members_returns_expired_after_thirty_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->signedAt(CarbonImmutable::parse('2023-10-22'))->createQuietly([
            'member_id' => $member->id,
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertSame(MandateReadinessStatus::Expired, $result[$member->id]->status);
    }

    public function test_for_members_returns_revoked_for_a_member_with_only_a_revoked_mandate(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->revoked(CarbonImmutable::parse('2026-01-01'))->createQuietly([
            'member_id' => $member->id,
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertSame(MandateReadinessStatus::Revoked, $result[$member->id]->status);
    }

    public function test_for_members_returns_the_latest_revoked_mandate_for_the_refund_iban(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->revoked(CarbonImmutable::parse('2025-01-01'))->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);
        $latest = PaymentInformation::factory()->revoked(CarbonImmutable::parse('2026-01-01'))->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL20RABO0123456789',
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertSame(MandateReadinessStatus::Revoked, $result[$member->id]->status);
        static::assertSame('NL20RABO0123456789', $result[$member->id]->iban);
        static::assertSame(
            new MandateId(MemberId::create($member->id), PaymentInformationId::create($latest->id))->value,
            $result[$member->id]->mandateId?->value,
        );
    }

    public function test_for_members_does_not_duplicate_a_member_with_mandate_history(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->revoked(CarbonImmutable::parse('2025-01-01'))->createQuietly([
            'member_id' => $member->id,
        ]);
        $active = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL20RABO0123456789',
        ]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertCount(1, $result);
        static::assertSame(MandateReadinessStatus::Active, $result[$member->id]->status);
        static::assertSame('NL20RABO0123456789', $result[$member->id]->iban);
        static::assertSame(
            new MandateId(MemberId::create($member->id), PaymentInformationId::create($active->id))->value,
            $result[$member->id]->mandateId?->value,
        );
    }

    public function test_for_members_keeps_expected_sequence_type_recurring_until_plan_05(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $result = $this->service->forMembers([MemberId::create($member->id)]);

        static::assertSame(SequenceType::Recurring, $result[$member->id]->expectedSequenceType);
    }

    public function test_for_members_keys_the_result_by_member_id(): void
    {
        [$first, $second] = Member::factory()->count(2)->createQuietly()->all();

        PaymentInformation::factory()->createQuietly(['member_id' => $first->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $second->id]);

        $result = $this->service->forMembers([
            MemberId::create($first->id),
            MemberId::create($second->id),
        ]);

        static::assertEqualsCanonicalizing(
            [$first->id, $second->id],
            array_keys($result),
        );
        static::assertSame(MandateReadinessStatus::Active, $result[$first->id]->status);
        static::assertSame(MandateReadinessStatus::Active, $result[$second->id]->status);
    }

    public function test_for_members_returns_an_empty_array_without_members(): void
    {
        static::assertSame([], $this->service->forMembers([]));
    }
}
