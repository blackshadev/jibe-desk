<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use App\Domain\Members\Gender;
use App\Domain\Registration\PersonalInfoData;
use Illuminate\Foundation\Http\FormRequest;

final class StorePersonalInformationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'infix_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'gender' => ['required', 'string', 'in:' . implode(',', array_column(Gender::cases(), 'value'))],
            'birthdate' => ['required', 'date', 'before:today'],
            'address_street' => ['required', 'string', 'max:255'],
            'address_housenumber' => ['required', 'string', 'max:20'],
            'address_housenumber_addition' => ['nullable', 'string', 'max:20'],
            'address_postalcode' => ['required', 'string', 'regex:/^\d{4}[A-Z]{2}$/'],
            'address_city' => ['required', 'string', 'max:255'],
        ];
    }

    public function toPersonalInfoData(): PersonalInfoData
    {
        return new PersonalInfoData(
            firstName: (string) $this->string('first_name'),
            infixName: (string) $this->string('infix_name'),
            lastName: (string) $this->string('last_name'),
            email: (string) $this->string('email'),
            gender: (string) $this->string('gender'),
            birthdate: (string) $this->string('birthdate'),
            addressStreet: (string) $this->string('address_street'),
            addressHousenumber: (string) $this->string('address_housenumber'),
            addressHousenumberAddition: (string) $this->string('address_housenumber_addition'),
            addressPostalcode: (string) $this->string('address_postalcode'),
            addressCity: (string) $this->string('address_city'),
        );
    }
}
