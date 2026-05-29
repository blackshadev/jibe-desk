<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\Activity;
use App\Models\BillableItem;
use App\Models\ExtraMembershipItem;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Hash;
use Illuminate\Database\Seeder;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->createQuietly([
            'email' => 'test@test.nl',
            'password' => Hash::make('password'),
        ]);

        $memberships = Membership::factory()->createMany([
            ['name' => 'Regulier'],
            ['name' => 'Kind'],
            ['name' => 'Bestuurslid'],
        ]);

        $activities = Activity::factory()
            ->createMany([
                ['name' => 'Reguliere les volwassenen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30'],
                ['name' => 'Reguliere les kinderen', 'start_date' => '2026-04-01', 'end_date' => '2026-10-30'],
                ['name' => 'RTC 1', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
                ['name' => 'RTC 2', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
                ['name' => 'RTC 4', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30'],
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => 20,
                'vat' => 4.2,
                'bill_period' => 'annually',
                'description' => 'Vrijwilligersbijdrage',
            ]))
            ->create([
                'code' => ExtraMembershipItemCode::VolunteerContribution,
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -22,
                'vat' => -4.62,
                'bill_period' => 'annually',
                'description' => 'Vrijwilligersbijdrage restitutie',
            ]))
            ->create([
                'code' => ExtraMembershipItemCode::VolunteerRestitution,
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -4.5,
                'vat' => -0.945,
                'bill_period' => 'annually',
                'description' => 'Zelfde adres korting jeugd',
            ]))
            ->create([
                'code' => 'zelfde_adres_korting_jeugd',
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -8,
                'vat' => -1.68,
                'bill_period' => 'annually',
                'description' => 'Zelfde adres korting volwassenen',
            ]))
            ->create([
                'code' => 'zelfde_adres_korting_volwassen',
            ]);

        $members = collect();
        foreach ($memberships as $membership) {
            $members = $members->merge(
                Member::factory()
                    ->count(10)
                    ->for($membership)
                    ->withRandomActivity($activities)
                    ->createMany()
            );
        }

        foreach ($members as $member) {
            Invoice::factory()
                ->forMember($member)
                ->withLines()
                ->randomCount()
                ->create();
        }
    }
}
