<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Registration\FormData;
use App\Domain\Registration\FormDataRepository;
use Illuminate\Support\Facades\Session;

final readonly class FormDataSessionRepository implements FormDataRepository
{
    private const SESSION_KEY = 'registration_form_data';

    public function get(): FormData
    {
        $formData = Session::get(self::SESSION_KEY);

        if (empty($formData)) {
            return FormData::createDefault();
        }

        return FormData::create($formData);
    }

    public function save(FormData $formData): void
    {
        Session::put(self::SESSION_KEY, $formData->toArray());
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
