<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\FactoryImmutable;
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
    }

    public function boot(): void
    {
    }
}
