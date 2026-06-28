<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\MemberAdministrationRecipient;
use App\Domain\Mail\Recipient;
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
        $this->app->bind(SepaConfiguration::class, static fn () => new SepaConfiguration(
            creditorId: config('sepa.creditor_id') ?? '',
            creditorName: config('sepa.creditor_name') ?? '',
            creditorIban: config('sepa.creditor_iban') ?? '',
            creditorBic: config('sepa.creditor_bic') ?? '',
        ));

        $this->app
            ->bind(MemberAdministrationRecipient::class, static fn () => new MemberAdministrationRecipient(
                recipient: new Recipient(
                    name: config('mail.admin.name'),
                    email: config('mail.admin.address'),
                ),
            ));

        $this->app
            ->bind(FinancialAdministrationRecipient::class, static fn () => new FinancialAdministrationRecipient(
                recipient: new Recipient(
                    name: config('mail.invoicing.name'),
                    email: config('mail.invoicing.address'),
                ),
            ));
    }

    public function boot(): void
    {
        //        RateLimiter::for('invoice-emails', static fn (): Limit => Limit::perMinute(20));
    }
}
