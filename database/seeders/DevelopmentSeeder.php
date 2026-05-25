<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Members\ExtraMembershipItemCode;
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
