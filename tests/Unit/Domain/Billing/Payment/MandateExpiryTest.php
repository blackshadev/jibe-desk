<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateExpiry;
use App\Domain\Billing\Payment\MandateState;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\UnitTestCase;

final class MandateExpiryTest extends UnitTestCase
{
    private ClockExpectation $clock;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = ClockExpectation::create();
    }

    public function test_state_returns_active_thirty_five_months_after_the_last_collection(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2026-12-15'));

        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2023-01-15'),
            lastCollectedAt: CarbonImmutable::parse('2024-01-15'),
            revokedAt: null,
        );

        static::assertSame(MandateState::Active, $state);
    }

    public function test_state_returns_expired_at_exactly_thirty_six_months(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2027-01-15'));

        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2023-01-15'),
            lastCollectedAt: CarbonImmutable::parse('2024-01-15'),
            revokedAt: null,
        );

        static::assertSame(MandateState::Expired, $state);
    }

    public function test_state_returns_expired_thirty_seven_months_after_the_last_collection(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2027-02-15'));

        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2023-01-15'),
            lastCollectedAt: CarbonImmutable::parse('2024-01-15'),
            revokedAt: null,
        );

        static::assertSame(MandateState::Expired, $state);
    }

    public function test_state_counts_from_the_signing_date_when_the_mandate_was_never_collected(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2026-01-15'));

        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2023-01-15'),
            lastCollectedAt: null,
            revokedAt: null,
        );

        static::assertSame(MandateState::Expired, $state);
    }

    public function test_state_counts_from_the_last_collection_and_not_the_signing_date(): void
    {
        $this->clock->expectsNow(CarbonImmutable::parse('2026-01-15'));

        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2020-01-15'),
            lastCollectedAt: CarbonImmutable::parse('2024-01-15'),
            revokedAt: null,
        );

        static::assertSame(MandateState::Active, $state);
    }

    public function test_state_returns_revoked_even_when_the_mandate_is_also_expired(): void
    {
        $state = $this->expiry()->state(
            signedAt: CarbonImmutable::parse('2020-01-15'),
            lastCollectedAt: null,
            revokedAt: CarbonImmutable::parse('2021-01-15'),
        );

        static::assertSame(MandateState::Revoked, $state);
    }

    public function test_expires_at_clamps_month_ends_without_overflow(): void
    {
        $expiry = $this->expiry();

        static::assertSame(
            '2027-01-31',
            $expiry->expiresAt(CarbonImmutable::parse('2024-01-31'), null)->format('Y-m-d'),
        );
        static::assertSame(
            '2027-02-28',
            $expiry->expiresAt(CarbonImmutable::parse('2024-02-29'), null)->format('Y-m-d'),
        );
    }

    private function expiry(): MandateExpiry
    {
        return new MandateExpiry($this->clock->mock);
    }
}
