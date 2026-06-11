<?php

declare(strict_types=1);

namespace App\Domain\Registration;

enum Step: int
{
    case Initial = -1;
    case Welcome = 0;
    case Membership = 1;
    case PersonalInfo = 2;
    case PaymentInfo = 3;
    case Confirmation = 4;
}
