<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\Billing\Payment\MandateExpirationStatus;
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
        static::assertSame($active->id, $member->currentPaymentInformation?->id);
        static::assertCount(2, $member->paymentInformations);
        static::assertSame($active->id, $member->paymentInformations->first()?->id);
        static::assertSame($revoked->id, $member->paymentInformations->last()?->id);
    }

    public function test_current_payment_information_falls_back_to_the_most_recent_revoked_mandate(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly(['member_id' => $member->id]);
        $latest = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['member_id' => $member->id]);

        static::assertNull($member->paymentInformation);
        static::assertSame($latest->id, $member->currentPaymentInformation?->id);
    }

    public function test_current_payment_information_prefers_the_active_mandate_over_a_newer_revoked_one(): void
    {
        $member = Member::factory()->createQuietly();
        $active = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['member_id' => $member->id]);

        static::assertSame($active->id, $member->currentPaymentInformation?->id);

        $reloaded = Member::query()->with('currentPaymentInformation')->findOrFail($member->id);

        static::assertSame($active->id, $reloaded->currentPaymentInformation?->id);
    }

    public function test_expiration_status_returns_active_for_a_non_revoked_mandate(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly();

        static::assertSame(MandateExpirationStatus::Active, $mandate->expiration_status);
    }

    public function test_expiration_status_returns_expired_after_thirty_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-10-22'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Expired, $mandate->expiration_status);
    }

    public function test_expiration_status_returns_revoked(): void
    {
        $mandate = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Revoked, $mandate->expiration_status);
    }

    public function test_expiration_status_is_active_thirty_five_months_after_the_last_collection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-15'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-01-15'))
            ->collectedAt(CarbonImmutable::parse('2024-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Active, $mandate->expiration_status);
    }

    public function test_expiration_status_is_expired_at_exactly_thirty_six_months_after_the_last_collection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-15'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-01-15'))
            ->collectedAt(CarbonImmutable::parse('2024-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Expired, $mandate->expiration_status);
    }

    public function test_expiration_status_is_expired_thirty_seven_months_after_the_last_collection(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-02-15'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-01-15'))
            ->collectedAt(CarbonImmutable::parse('2024-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Expired, $mandate->expiration_status);
    }

    public function test_expiration_status_counts_from_the_signing_date_when_the_mandate_was_never_collected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2023-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Expired, $mandate->expiration_status);
    }

    public function test_expiration_status_counts_from_the_last_collection_and_not_the_signing_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15'));

        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2020-01-15'))
            ->collectedAt(CarbonImmutable::parse('2024-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Active, $mandate->expiration_status);
    }

    public function test_expiration_status_returns_revoked_even_when_the_mandate_is_also_expired(): void
    {
        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2020-01-15'))
            ->revoked(CarbonImmutable::parse('2021-01-15'))
            ->createQuietly();

        static::assertSame(MandateExpirationStatus::Revoked, $mandate->expiration_status);
    }

    public function test_expires_at_clamps_month_ends_without_overflow(): void
    {
        $january = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2024-01-31'))
            ->createQuietly();
        $february = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2024-02-29'))
            ->createQuietly();

        static::assertSame('2027-01-31', $january->expires_at->format('Y-m-d'));
        static::assertSame('2027-02-28', $february->expires_at->format('Y-m-d'));
    }

    public function test_expires_at_uses_the_last_collection_when_present(): void
    {
        $mandate = PaymentInformation::factory()
            ->signedAt(CarbonImmutable::parse('2020-01-31'))
            ->collectedAt(CarbonImmutable::parse('2024-02-29'))
            ->createQuietly();

        static::assertSame('2027-02-28', $mandate->expires_at->format('Y-m-d'));
    }
}
