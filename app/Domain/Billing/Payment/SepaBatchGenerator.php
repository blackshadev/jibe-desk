<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaBatchGenerator
{
    public function generate(SepaBatch $sepaBatch): SepaBatchId;
}
