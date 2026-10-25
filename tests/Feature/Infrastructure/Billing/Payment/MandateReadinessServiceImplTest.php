<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Members\MemberId;
use App\Infrastructure\Billing\Payment\MandateReadinessServiceImpl;
use App\Models\Member;
use App\Models\PaymentInformation;
use Tests\FeatureTestCase;

final class MandateReadinessServiceImplTest extends FeatureTestCase
{
    public function test_for_members_returns_an_active_mandate_with_iban_and_reference(): void
    {
        $member = Member::factory()->createQuietly();
        $paymentInformation = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $result = new MandateReadinessServiceImpl()->forMembers([MemberId::create($member->id)]);

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

        $result = new MandateReadinessServiceImpl()->forMembers([MemberId::create($member->id)]);

        static::assertArrayNotHasKey($member->id, $result);
    }

    public function test_for_members_ignores_a_soft_deleted_mandate(): void
    {
        $member = Member::factory()->createQuietly();
        $paymentInformation = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        $paymentInformation->delete();

        $result = new MandateReadinessServiceImpl()->forMembers([MemberId::create($member->id)]);

        static::assertArrayNotHasKey($member->id, $result);
    }

    public function test_for_members_keys_the_result_by_member_id(): void
    {
        [$first, $second] = Member::factory()->count(2)->createQuietly()->all();

        PaymentInformation::factory()->createQuietly(['member_id' => $first->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $second->id]);

        $result = new MandateReadinessServiceImpl()->forMembers([
            MemberId::create($first->id),
            MemberId::create($second->id),
        ]);

        static::assertSame(
            [$first->id, $second->id],
            array_keys($result),
        );
        static::assertSame(MandateReadinessStatus::Active, $result[$first->id]->status);
        static::assertSame(MandateReadinessStatus::Active, $result[$second->id]->status);
    }

    public function test_for_members_returns_an_empty_array_without_members(): void
    {
        static::assertSame([], new MandateReadinessServiceImpl()->forMembers([]));
    }
}
