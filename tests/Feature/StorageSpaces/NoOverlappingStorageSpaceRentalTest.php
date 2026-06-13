<?php

declare(strict_types=1);

namespace Tests\Feature\StorageSpaces;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use App\Rules\NoOverlappingStorageSpaceRental;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Tests\FeatureTestCase;

final class NoOverlappingStorageSpaceRentalTest extends FeatureTestCase
{
    private StorageSpace $storageSpace;

    private Member $member;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->storageSpace = StorageSpace::factory()->createOneQuietly();
        $this->member = Member::factory()->createOneQuietly();
    }

    public function test_passes_when_no_overlap(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-07-01'),
            endDate: CarbonImmutable::create('2026-12-31'),
        );

        self::assertValidateRule(true, $rule, 'start_date', '2026-07-01');
    }

    public function test_fails_when_fully_contained(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-03-01'),
            endDate: CarbonImmutable::create('2026-05-31'),
        );

        self::assertValidateRule(false, $rule, 'start_date', '2026-03-01');
    }

    public function test_passes_when_touching_at_start_boundary(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-06-01'),
            'end_date' => CarbonImmutable::create('2026-12-31'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-01-01'),
            endDate: CarbonImmutable::create('2026-06-01'),
        );

        self::assertValidateRule(true, $rule, 'start_date', '2026-01-01');
    }

    public function test_fails_with_open_ended_existing(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => null,
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-03-01'),
            endDate: CarbonImmutable::create('2026-06-30'),
        );

        self::assertValidateRule(false, $rule, 'start_date', '2026-03-01');
    }

    public function test_fails_with_open_ended_new(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => CarbonImmutable::create('2026-06-30'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-03-01'),
            endDate: null,
        );

        self::assertValidateRule(false, $rule, 'start_date', '2026-03-01');
    }

    public function test_fails_when_both_open_ended(): void
    {
        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => null,
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-02-01'),
            endDate: null,
        );

        self::assertValidateRule(false, $rule, 'start_date', '2026-02-01');
    }

    public function test_passes_when_excluding_self_on_edit(): void
    {
        $rental = StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => CarbonImmutable::create('2026-12-31'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-01-01'),
            endDate: CarbonImmutable::create('2026-12-31'),
            excludeRentalIds: [$rental->id],
        );

        self::assertValidateRule(true, $rule, 'start_date', '2026-01-01');
    }

    public function test_fails_when_different_member_same_space(): void
    {
        $otherMember = Member::factory()->createOneQuietly();

        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $otherMember->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => CarbonImmutable::create('2026-12-31'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-03-01'),
            endDate: CarbonImmutable::create('2026-06-30'),
        );

        self::assertValidateRule(false, $rule, 'start_date', '2026-03-01');
    }

    public function test_passes_when_same_member_different_space(): void
    {
        $otherSpace = StorageSpace::factory()->createOne();

        StorageSpaceRental::factory()->createOne([
            'storage_space_id' => $otherSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => CarbonImmutable::create('2026-12-31'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-03-01'),
            endDate: CarbonImmutable::create('2026-06-30'),
        );

        self::assertValidateRule(true, $rule, 'start_date', '2026-03-01');
    }

    public function test_passes_when_touching_end_boundary(): void
    {
        StorageSpaceRental::factory()->createOneQuietly([
            'storage_space_id' => $this->storageSpace->id,
            'member_id' => $this->member->id,
            'start_date' => CarbonImmutable::create('2026-01-01'),
            'end_date' => CarbonImmutable::create('2026-06-30'),
        ]);

        $rule = new NoOverlappingStorageSpaceRental(
            storageSpaceId: $this->storageSpace->id,
            startDate: CarbonImmutable::create('2026-06-30'),
            endDate: CarbonImmutable::create('2026-12-31'),
        );

        self::assertValidateRule(true, $rule, 'start_date', '2026-06-30');
    }

    // @mago-ignore lint:no-boolean-flag-parameter
    private static function assertValidateRule(bool $passes, ValidationRule $rule, string $attr, string $value): void
    {
        $failed = false;
        $reason = null;
        $rule->validate($attr, $value, static function (string $r) use (&$failed, &$reason): PotentiallyTranslatedString {
            $failed = true;
            $reason = $r;

            return new PotentiallyTranslatedString($r, app('translator'));
        });

        if ($passes) {
            self::assertFalse($failed, 'Expected rule to pass, but it failed with message: ' . $reason);
            return;
        }

        self::assertTrue($failed, 'Expected rule to fail, but it passed');
    }
}
