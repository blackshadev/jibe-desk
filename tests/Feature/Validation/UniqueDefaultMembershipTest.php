<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\Membership;
use App\Rules\UniqueDefaultMembership;
use Database\Seeders\MembershipSeeder;
use Illuminate\Support\Facades\Validator;
use Tests\FeatureTestCase;

final class UniqueDefaultMembershipTest extends FeatureTestCase
{
    public function test_validation_passes_when_no_default_exists(): void
    {
        $validator = Validator::make(
            ['is_default' => true],
            ['is_default' => [new UniqueDefaultMembership()]],
        );

        static::assertTrue($validator->passes());
    }

    public function test_validation_fails_when_another_is_already_default(): void
    {
        $this->seed(MembershipSeeder::class);

        Membership::query()->first()->update(['is_default' => true]);

        $validator = Validator::make(
            ['is_default' => true],
            ['is_default' => [new UniqueDefaultMembership()]],
        );

        static::assertTrue($validator->fails());
        static::assertArrayHasKey('is_default', $validator->errors()->toArray());
    }

    public function test_validation_passes_when_setting_false(): void
    {
        $this->seed(MembershipSeeder::class);

        Membership::query()->first()->update(['is_default' => true]);

        $validator = Validator::make(
            ['is_default' => false],
            ['is_default' => [new UniqueDefaultMembership()]],
        );

        static::assertTrue($validator->passes());
    }

    public function test_validation_passes_when_excluding_self(): void
    {
        $this->seed(MembershipSeeder::class);

        $membership = Membership::query()->first();
        $membership->update(['is_default' => true]);

        $validator = Validator::make(
            ['is_default' => true],
            ['is_default' => [new UniqueDefaultMembership($membership?->id)]],
        );

        static::assertTrue($validator->passes());
    }

    public function test_validation_fails_when_another_is_default_even_with_exclude(): void
    {
        $this->seed(MembershipSeeder::class);

        $memberships = Membership::query()->get();
        $memberships->first()->update(['is_default' => true]);

        $validator = Validator::make(
            ['is_default' => true],
            ['is_default' => [new UniqueDefaultMembership($memberships->last()?->id)]],
        );

        static::assertTrue($validator->fails());
    }
}
