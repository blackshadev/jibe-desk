<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;
use Override;

final class ConfirmRegistrationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'confirm_data_correct' => ['accepted'],
            'confirm_membership' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'confirm_data_correct.accepted' => 'Bevestig dat alle gegevens correct zijn ingevuld.',
            'confirm_membership.accepted' => 'Bevestig dat je lid wilt worden van de vereniging.',
        ];
    }
}
