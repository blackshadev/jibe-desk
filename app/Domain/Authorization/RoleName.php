<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

enum RoleName: string
{
    case MemberAdministration = 'member_administration';
    case FinancialAdministration = 'financial_administration';
    case ActivityAdministration = 'activity_administration';
    case TechnicalAdministration = 'technical_administration';
    case RentalAdministration = 'rental_administration';
}
