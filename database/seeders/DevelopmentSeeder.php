<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\RoleName;
use App\Models\Activity;
use App\Models\BankAccount;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Hash;
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
                    ->createMany(),
            );
        }

        BankAccount::factory()->create();
        BankAccount::factory()->create([
            'iban' => 'NL35RABO3010166281',
            'name' => 'Betaalrekening',
        ]);

        Member::factory()
            ->stopped()
            ->count(100)
            ->state([
                'membership_id' => $memberships->first()?->id,
            ])
            ->withPaymentInfo()
            ->createQuietly();
    }
}
