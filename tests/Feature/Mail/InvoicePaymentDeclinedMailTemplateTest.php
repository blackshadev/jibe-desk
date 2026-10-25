<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Markdown;
use Tests\FeatureTestCase;

final class InvoicePaymentDeclinedMailTemplateTest extends FeatureTestCase
{
    public function test_it_states_the_failed_debit_and_reversed_amount(): void
    {
        $html = $this->render(mandateNeedsReauthorisation: false);

        static::assertStringContainsString('I-20261001', $html);
        static::assertStringContainsString('01-06-2026', $html);
        static::assertStringContainsString('€ 100,00', $html);
        static::assertStringContainsString('NL91ABNA0417164300', $html);
        static::assertStringContainsString('Watersportvereniging Almere Centraal', $html);
        static::assertStringContainsString('factuurnummer', $html);
    }

    public function test_it_states_no_decline_reason(): void
    {
        $html = $this->render(mandateNeedsReauthorisation: false);

        static::assertStringContainsString('is niet gelukt', $html);
        static::assertStringContainsString('teruggeboekt', $html);
        static::assertStringNotContainsString('onvoldoende saldo', $html);
        static::assertStringNotContainsString('machtiging geweigerd', $html);
        static::assertStringNotContainsString('terugboekingscode', $html);
        static::assertStringNotContainsString('reden', $html);
    }

    public function test_it_renders_the_reauthorisation_hint_when_the_flag_is_set(): void
    {
        $html = $this->render(mandateNeedsReauthorisation: true);

        static::assertStringContainsString('nieuwe machtiging', $html);
        static::assertStringContainsString('ingetrokken of verlopen', $html);
    }

    public function test_it_hides_the_reauthorisation_hint_when_the_flag_is_not_set(): void
    {
        $html = $this->render(mandateNeedsReauthorisation: false);

        static::assertStringNotContainsString('nieuwe machtiging', $html);
    }

    private function render(bool $mandateNeedsReauthorisation): string
    {
        return $this->app
            ->make(Markdown::class)
            ->render('mail.invoice-payment-declined', [
                'memberName' => 'Jan de Vries',
                'invoiceNumber' => 'I-20261001',
                'invoiceDate' => CarbonImmutable::parse('2026-06-01'),
                'total' => new CompoundPrice(100.0, 21.0),
                'creditorIban' => 'NL91ABNA0417164300',
                'creditorAccountName' => 'Watersportvereniging Almere Centraal',
                'mandateNeedsReauthorisation' => $mandateNeedsReauthorisation,
            ])
            ->toHtml();
    }
}
