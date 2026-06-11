<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use App\Domain\Registration\PaymentInfoData;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Intervention\Validation\Rules\Bic;
use Intervention\Validation\Rules\Iban;

final class StorePaymentInformationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'banking_account_number' => ['required', new Iban()],
            'banking_bic' => ['required', new Bic()],
            'banking_account_holder_name' => ['required', 'string', 'max:255'],
            'mandate_accepted' => ['accepted'],
        ];
    }

    public function toPaymentInfoData(): PaymentInfoData
    {
        return new PaymentInfoData(
            bankingAccountNumber: (string) $this->string('banking_account_number'),
            bankingBic: (string) $this->string('banking_bic'),
            bankingAccountHolderName: (string) $this->string('banking_account_holder_name'),
            mandateAcceptedDate: new DateTimeImmutable(),
        );
    }
}
