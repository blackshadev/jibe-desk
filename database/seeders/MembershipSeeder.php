<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\BillableItem;
use App\Models\ExtraMembershipItem;
use App\Models\Membership;
use Illuminate\Database\Seeder;

final class MembershipSeeder extends Seeder
{
    private const MEMBERSHIPS = [
        ['name' => 'Windsurfer', 'adult_price' => 68, 'kids_price' => 43.5 ],
        ['name' => 'Zeiler', 'adult_price' => 68, 'kids_price' => 43.5 ],
        ['name' => 'Bestuurslid', 'adult_price' => 0, 'kids_price' => 0 ],
    ];

    public function run(): void
    {
        foreach (self::MEMBERSHIPS as $membership) {
            $adultBillableItem = BillableItem::create([
                'description' => "Lidmaatschap {$membership['name']} (volwassenen)",
                'price' => $membership['adult_price'],
                'vat' => $membership['adult_price'] * 0.21,
                'bill_period' => BillPeriod::Annually,
            ]);
            $kidsBillableItem = BillableItem::create([
                'description' => "Lidmaatschap {$membership['name']} (jeugd)",
                'price' => $membership['kids_price'],
                'vat' => $membership['kids_price'] * 0.21,
                'bill_period' => BillPeriod::Annually,
            ]);

            Membership::create([
                'name' => $membership['name'],
                'adult_billable_item_id' => $adultBillableItem->id,
                'kids_billable_item_id' => $kidsBillableItem->id,
            ]);
        }

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => 20,
                'vat' => 4.2,
                'bill_period' => BillPeriod::Annually,
                'description' => 'Vrijwilligersbijdrage',
            ]))
            ->create([
                'code' => ExtraMembershipItemCode::VolunteerContribution,
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -22,
                'vat' => -4.62,
                'bill_period' => BillPeriod::Annually,
                'description' => 'Vrijwilligersbijdrage restitutie',
            ]))
            ->create([
                'code' => ExtraMembershipItemCode::VolunteerRestitution,
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -4.5,
                'vat' => -0.945,
                'bill_period' => BillPeriod::Annually,
                'description' => 'Zelfde huishouden korting jeugd',
            ]))
            ->create([
                'code' => 'zelfde_huishouden_korting_jeugd',
            ]);

        ExtraMembershipItem::factory()
            ->for(BillableItem::factory()->state([
                'price' => -8,
                'vat' => -1.68,
                'bill_period' => BillPeriod::Annually,
                'description' => 'Zelfde huishouden korting volwassenen',
            ]))
            ->create([
                'code' => 'zelfde_huishouden_korting_volwassen',
            ]);
    }
}
