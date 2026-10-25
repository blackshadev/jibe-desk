<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Payment\MandateCorrection;
use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\PaymentInformationIdList;
use App\Domain\Members\MemberId;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Override;
use Tests\FeatureTestCase;

final class MandateRepositoryDbTest extends FeatureTestCase
{
    private MandateRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(MandateRepository::class);
    }

    public function test_register_creates_the_first_active_mandate(): void
    {
        $member = Member::factory()->createQuietly();

        $id = $this->repository->register(
            MemberId::create($member->id),
            new MandateDetails(
                iban: 'NL91ABNA0417164300',
                bic: 'ABNANL2A',
                accountHolderName: 'Jan de Vries',
                signedAt: CarbonImmutable::parse('2026-01-15'),
            ),
        );

        $mandate = PaymentInformation::query()->findOrFail($id->value);

        static::assertSame($member->id, $mandate->member_id);
        static::assertSame('NL91ABNA0417164300', $mandate->banking_account_number);
        static::assertSame('ABNANL2A', $mandate->banking_bic);
        static::assertSame('Jan de Vries', $mandate->banking_account_holder_name);
        static::assertSame('2026-01-15', $mandate->mandate_accepted_date->format('Y-m-d'));
        static::assertNull($mandate->revoked_at);
        static::assertNull($mandate->last_collected_at);
    }

    public function test_register_creates_a_new_active_mandate_and_revokes_the_old_one(): void
    {
        $member = Member::factory()->createQuietly();
        $old = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $newId = $this->repository->register(
            MemberId::create($member->id),
            new MandateDetails(
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
                accountHolderName: 'Jan de Vries',
                signedAt: CarbonImmutable::parse('2026-10-01'),
            ),
        );

        $old->refresh();
        $new = PaymentInformation::query()->findOrFail($newId->value);

        static::assertNotNull($old->revoked_at);
        static::assertNull($new->revoked_at);
        static::assertSame(1, PaymentInformation::query()->where('member_id', $member->id)->whereNull('revoked_at')->count());
    }

    public function test_register_keeps_the_iban_on_the_revoked_mandate(): void
    {
        $member = Member::factory()->createQuietly();
        $old = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $this->repository->register(
            MemberId::create($member->id),
            new MandateDetails(
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
                accountHolderName: 'Jan de Vries',
                signedAt: CarbonImmutable::parse('2026-10-01'),
            ),
        );

        static::assertSame('NL91ABNA0417164300', $old->refresh()->banking_account_number);
    }

    public function test_register_creates_a_new_reference_so_the_first_collection_is_first_again(): void
    {
        $member = Member::factory()->createQuietly();
        $old = PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);
        $oldReference = $old->mandate_reference;

        $newId = $this->repository->register(
            MemberId::create($member->id),
            new MandateDetails(
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
                accountHolderName: 'Jan de Vries',
                signedAt: CarbonImmutable::parse('2026-10-01'),
            ),
        );

        $newReference = new MandateId(MemberId::create($member->id), $newId)->value;

        static::assertNotSame($oldReference, $newReference);
        static::assertSame($oldReference, $old->refresh()->mandate_reference);
    }

    public function test_correct_details_changes_the_bic_and_account_holder_name(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly();

        $this->repository->correctDetails(
            PaymentInformationId::create($mandate->id),
            new MandateCorrection(bic: 'RABONL2U', accountHolderName: 'J. de Vries'),
        );

        $mandate->refresh();

        static::assertSame('RABONL2U', $mandate->banking_bic);
        static::assertSame('J. de Vries', $mandate->banking_account_holder_name);
    }

    public function test_correct_details_leaves_the_iban_and_signed_date_untouched(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly([
            'banking_account_number' => 'NL91ABNA0417164300',
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $this->repository->correctDetails(
            PaymentInformationId::create($mandate->id),
            new MandateCorrection(bic: 'RABONL2U', accountHolderName: 'J. de Vries'),
        );

        $mandate->refresh();

        static::assertSame('NL91ABNA0417164300', $mandate->banking_account_number);
        static::assertSame('2025-01-15', $mandate->mandate_accepted_date->format('Y-m-d'));
    }

    public function test_correct_details_leaves_a_revoked_mandate_untouched(): void
    {
        $mandate = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly(['banking_bic' => 'ABNANL2A']);

        $this->repository->correctDetails(
            PaymentInformationId::create($mandate->id),
            new MandateCorrection(bic: 'RABONL2U', accountHolderName: 'J. de Vries'),
        );

        static::assertSame('ABNANL2A', $mandate->refresh()->banking_bic);
    }

    public function test_revoke_sets_revoked_at_and_keeps_the_iban(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly([
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $this->repository->revoke(
            PaymentInformationId::create($mandate->id),
            CarbonImmutable::parse('2026-10-01 12:00:00'),
        );

        $mandate->refresh();

        static::assertSame('2026-10-01 12:00:00', $mandate->revoked_at?->format('Y-m-d H:i:s'));
        static::assertSame('NL91ABNA0417164300', $mandate->banking_account_number);
    }

    public function test_revoke_is_idempotent(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly();

        $this->repository->revoke(
            PaymentInformationId::create($mandate->id),
            CarbonImmutable::parse('2026-10-01 12:00:00'),
        );
        $this->repository->revoke(
            PaymentInformationId::create($mandate->id),
            CarbonImmutable::parse('2026-10-02 12:00:00'),
        );

        static::assertSame('2026-10-01 12:00:00', $mandate->refresh()->revoked_at?->format('Y-m-d H:i:s'));
    }

    public function test_stamp_collections_sets_last_collected_at(): void
    {
        $mandate = PaymentInformation::factory()->createQuietly();
        $collectedAt = CarbonImmutable::parse('2026-10-23 09:30:00');

        $updated = $this->repository->stampCollections(
            new PaymentInformationIdList([PaymentInformationId::create($mandate->id)]),
            $collectedAt,
        );

        static::assertSame(1, $updated);
        static::assertSame('2026-10-23 09:30:00', $mandate->refresh()->last_collected_at?->format('Y-m-d H:i:s'));
    }

    public function test_stamp_collections_does_not_move_last_collected_at_backwards(): void
    {
        $mandate = PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-10-01 12:00:00'))
            ->createQuietly();

        $this->repository->stampCollections(
            new PaymentInformationIdList([PaymentInformationId::create($mandate->id)]),
            CarbonImmutable::parse('2026-09-01 12:00:00'),
        );

        static::assertSame('2026-10-01 12:00:00', $mandate->refresh()->last_collected_at?->format('Y-m-d H:i:s'));
    }

    public function test_stamp_collections_ignores_an_empty_list(): void
    {
        static::assertSame(0, $this->repository->stampCollections(
            new PaymentInformationIdList([]),
            CarbonImmutable::parse('2026-10-01 12:00:00'),
        ));
    }

    public function test_one_active_mandate_per_member_is_enforced_by_the_database(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->expectException(QueryException::class);

        PaymentInformation::factory()->create(['member_id' => $member->id]);
    }
}
