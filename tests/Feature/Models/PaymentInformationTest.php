<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\Billing\Payment\MandateExpiry;
use App\Domain\Billing\Payment\MandateState;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Override;
use Tests\FeatureTestCase;

final class PaymentInformationTest extends FeatureTestCase
{
    #[Override]
    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_existing_mandate_keeps_its_derived_reference_after_the_lifecycle_migration(): void
    {
        $member = Member::factory()->createQuietly(['id' => 42]);
        $mandate = PaymentInformation::factory()->createQuietly([
            'id' => 7,
            'member_id' => $member->id,
        ]);

        static::assertSame('C000042-000007', $mandate->mandate_reference);
    }

    public function test_a_member_can_have_a_revoked_history_row_and_one_active_row(): void
    {
        $member = Member::factory()->createQuietly();
        $revoked = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly(['member_id' => $member->id]);
        $active = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        static::assertSame($active->id, $member->paymentInformation?->id);
        static::assertCount(2, $member->paymentInformations);
        static::assertSame($active->id, $member->paymentInformations->first()?->id);
        static::assertSame($revoked->id, $member->paymentInformations->last()?->id);
    }

    public function test_state_returns_active_for_a_non_revoked_mandate(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly();

        static::assertSame(MandateState::Active, $mandate->state(app(MandateExpiry::class)));
    }

    public function test_state_returns_expired_after_thirty_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-10-22'))
            ->createQuietly();

        static::assertSame(MandateState::Expired, $mandate->state(app(MandateExpiry::class)));
    }

    public function test_state_returns_revoked(): void
    {
        $mandate = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly();

        static::assertSame(MandateState::Revoked, $mandate->state(app(MandateExpiry::class)));
    }
}
