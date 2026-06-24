<?php

declare(strict_types=1);

return [
    'creditor_id' => env('SEPA_CREDITOR_ID'),
    'creditor_name' => env('SEPA_CREDITOR_NAME', 'WSV Almere Centraal'),
    'creditor_iban' => env('SEPA_CREDITOR_IBAN'),
    'creditor_bic' => env('SEPA_CREDITOR_BIC'),
];
