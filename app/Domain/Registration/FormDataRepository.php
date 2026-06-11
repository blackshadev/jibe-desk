<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface FormDataRepository
{
    public function get(): FormData;

    public function save(FormData $formData): void;

    public function clear(): void;
}
