<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Invoices\SepaConfiguration;
use Carbon\FactoryImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;

final class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        ClockInterface::class => FactoryImmutable::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->app->bind(SepaConfiguration::class, static fn () => new SepaConfiguration(
            creditorId: config('sepa.creditor_id') ?? '',
            creditorName: config('sepa.creditor_name') ?? '',
            creditorIban: config('sepa.creditor_iban') ?? '',
            creditorBic: config('sepa.creditor_bic') ?? '',
        ));
    }

    public function boot(): void
    {
        RateLimiter::for('invoice-emails', static fn (): Limit => Limit::perMinute(20));
    }
}
