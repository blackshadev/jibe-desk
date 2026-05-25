<?php

declare(strict_types=1);

namespace App\Domain\Members;

enum Gender: string
{
    case Male = 'M';
    case Female = 'F';
    case NonBinary = 'NB';
    case Undetermined = 'U';
    case Other = 'O';
}
