<?php

declare(strict_types=1);

use App\Console\Commands\GenerateInvoiceBatchCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateInvoiceBatchCommand::class)->monthlyOn(1, '04:00');
