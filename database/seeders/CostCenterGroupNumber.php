<?php

declare(strict_types=1);

namespace Database\Seeders;

enum CostCenterGroupNumber: string
{
    case OtherReceivables = '11';
    case LessonIncome = '31';
    case GeneralIncome = '101';

    public function name(): string
    {
        return match ($this) {
            self::OtherReceivables => 'Overige vorderingen',
            self::LessonIncome => 'inkomsten lessen',
            self::GeneralIncome => 'inkomsten algemeen',
        };
    }
}
