<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Helpers;

use App\Domain\Members\MemberNameFormatter;
use App\Models\Member;

final class MemberCreditorPrefill
{
    /** @return array{creditor_name: non-falsy-string, creditor_iban: ?string} */
    public static function for(Member $member): array
    {
        $member->loadMissing('paymentInformation');

        return [
            'creditor_name' => MemberNameFormatter::presentationName(
                $member->first_name,
                $member->infix_name,
                $member->last_name,
            ),
            'creditor_iban' => $member->paymentInformation->banking_account_number,
        ];
    }
}
