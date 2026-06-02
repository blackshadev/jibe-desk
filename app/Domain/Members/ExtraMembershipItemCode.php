<?php

declare(strict_types=1);

namespace App\Domain\Members;

enum ExtraMembershipItemCode: string
{
    case VolunteerRestitution = 'vrijwilliger_restitutie';
    case VolunteerContribution = 'vrijwilligers_bijdrage';
    case SameHouseholdDiscountYoungster = 'zelfde_adres_korting_jeugd';
    case SameHouseholdDiscountAdult = 'zelfde_adres_korting_volwassen';
}
