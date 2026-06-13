<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use App\Domain\Registration\MembershipData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreMembershipRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|string|array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'windsurfing_lessons' => 'boolean',
            'rtc_lessons' => 'boolean',
            'club_access' => 'boolean',
            'storage' => 'boolean',
            'watersport_federation_number' => ['nullable', 'string', 'max:9', 'regex:/^[0-9]+$/'],
        ];
    }

    /**
     * @return array<int, \Closure|object>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasSelection = $this->boolean('windsurfing_lessons') || $this->boolean('rtc_lessons') || $this->boolean('club_access') || $this->boolean('storage');

                if (!$hasSelection) {
                    $validator->errors()->add(
                        'membership_activities',
                        __('validation.accept_atleast_one', ['attribute' => mb_strtolower(__('labels.activities'))]),
                    );
                }
            },
        ];
    }

    public function toMembershipData(): MembershipData
    {
        return new MembershipData(
            regularWindsurfingLessons: $this->boolean('windsurfing_lessons'),
            rtc: $this->boolean('rtc_lessons'),
            clubhouseAccess: $this->boolean('club_access'),
            boardStorage: $this->boolean('storage'),
            watersportFederationNumber: (string) $this->string('watersport_federation_number'),
        );
    }
}
