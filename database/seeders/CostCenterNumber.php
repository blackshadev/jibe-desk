<?php

declare(strict_types=1);

namespace Database\Seeders;

enum CostCenterNumber: int
{
    case Rtc = 8104;
    case Lessons = 3008;
    case Contribution = 8105;
    case RegistrationFee = 8115;
    case StorageRental = 8270;
    case Deposit = 500;
}
