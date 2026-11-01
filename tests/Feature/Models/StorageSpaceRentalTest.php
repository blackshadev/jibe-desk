<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use Carbon\CarbonImmutable;
use Tests\FeatureTestCase;

final class StorageSpaceRentalTest extends FeatureTestCase
{
    private ?StorageSpace $storageSpace = null;

    public function test_overlapping_finds_a_fully_contained_rental(): void
    {
        $this->givenRental('2026-01-01', '2026-12-31');

        static::assertTrue($this->overlaps('2026-03-01', '2026-05-31'));
    }

    public function test_overlapping_ignores_a_rental_that_ends_before_the_period(): void
    {
        $this->givenRental('2026-01-01', '2026-06-30');

        static::assertFalse($this->overlaps('2026-07-01', '2026-12-31'));
    }

    public function test_overlapping_ignores_a_rental_that_starts_after_the_period(): void
    {
        $this->givenRental('2026-07-01', '2026-12-31');

        static::assertFalse($this->overlaps('2026-01-01', '2026-06-30'));
    }

    public function test_overlapping_ignores_a_rental_touching_the_end_boundary(): void
    {
        $this->givenRental('2026-01-01', '2026-06-30');

        static::assertFalse($this->overlaps('2026-06-30', '2026-12-31'));
    }

    public function test_overlapping_ignores_a_rental_touching_the_start_boundary(): void
    {
        $this->givenRental('2026-06-01', '2026-12-31');

        static::assertFalse($this->overlaps('2026-01-01', '2026-06-01'));
    }

    public function test_overlapping_finds_an_open_ended_rental(): void
    {
        $this->givenRental('2026-01-01', null);

        static::assertTrue($this->overlaps('2026-03-01', '2026-06-30'));
    }

    public function test_overlapping_finds_a_closed_rental_from_an_open_ended_period(): void
    {
        $this->givenRental('2026-01-01', '2026-06-30');

        static::assertTrue($this->overlaps('2026-03-01', null));
    }

    public function test_overlapping_finds_an_open_ended_rental_from_an_open_ended_period(): void
    {
        $this->givenRental('2026-01-01', null);

        static::assertTrue($this->overlaps('2026-02-01', null));
    }

    public function test_overlapping_ignores_another_storage_space(): void
    {
        $otherSpace = StorageSpace::factory()->createOneQuietly();
        $this->givenRental('2026-01-01', null, $otherSpace);

        static::assertFalse($this->overlaps('2026-03-01', '2026-06-30'));
    }

    public function test_overlapping_excludes_the_given_rental_ids(): void
    {
        $rental = $this->givenRental('2026-01-01', '2026-12-31');

        $exists = StorageSpaceRental::query()
            ->overlapping(
                storageSpaceId: $this->storageSpace()->id,
                startDate: CarbonImmutable::parse('2026-01-01'),
                endDate: CarbonImmutable::parse('2026-12-31'),
                excludeRentalIds: [$rental->id],
            )
            ->exists();

        static::assertFalse($exists);
    }

    private function storageSpace(): StorageSpace
    {
        return $this->storageSpace ??= StorageSpace::factory()->createOneQuietly();
    }

    private function givenRental(string $startDate, ?string $endDate, ?StorageSpace $storageSpace = null): StorageSpaceRental
    {
        $storageSpaceId = ($storageSpace ?? $this->storageSpace())->id;

        return StorageSpaceRental::factory()->createQuietly([
            'storage_space_id' => $storageSpaceId,
            'member_id' => Member::factory()->createQuietly()->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function overlaps(string $startDate, ?string $endDate): bool
    {
        return StorageSpaceRental::query()
            ->overlapping(
                storageSpaceId: $this->storageSpace()->id,
                startDate: CarbonImmutable::parse($startDate),
                endDate: $endDate === null ? null : CarbonImmutable::parse($endDate),
            )
            ->exists();
    }
}
