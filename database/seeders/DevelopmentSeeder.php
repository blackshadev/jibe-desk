<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\RoleName;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use App\Models\StorageSpace;
use App\Models\StorageSpaceLocation;
use App\Models\User;
use Hash;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::factory()->createQuietly([
            'email' => 'test@test.nl',
            'password' => Hash::make('password'),
        ]);
        $testUser->assignRole(array_map(static fn (RoleName $role) => $role->value, RoleName::cases()));

        foreach (RoleName::cases() as $roleName) {
            $user = User::factory()->createQuietly([
                'email' => $roleName->value . '@test.nl',
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($roleName->value);
        }

        $location3 = StorageSpaceLocation::query()->where('name', 'Container 3')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location3->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(30)
            ->create();

        $location4 = StorageSpaceLocation::query()->where('name', 'Container 4')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location4->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(30)
            ->create();

        $location5 = StorageSpaceLocation::query()->where('name', 'Container 5')->firstOrFail();
        StorageSpace::factory()
            ->state([
                'storage_space_location_id' => $location5->id,
            ])
            ->sequence(static fn (Sequence $sequence) => ['number' => $sequence->index + 1])
            ->count(20)
            ->create();

        $activities = Activity::all();
        $memberships = Membership::all();
        $members = collect();
        foreach ($memberships as $membership) {
            $members = $members->merge(
                Member::factory()
                    ->count(10)
                    ->for($membership)
                    ->withPaymentInfo()
                    ->withRandomActivity($activities)
                    ->has(
                        Invoice::factory()
                            ->withLines()
                            ->randomCount(),
                    )
                    ->createMany(),
            );
        }

        Member::factory()
            ->deleted()
            ->count(100)
            ->state([
                'membership_id' => $memberships->first()?->id,
            ])
            ->withPaymentInfo()
            ->createQuietly();
    }
}
