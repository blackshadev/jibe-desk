<?php

declare(strict_types=1);

namespace App\Domain\Registration;

enum RegistrationSource
{
    case RegistrationForm;
    case AdminPanel;
}
