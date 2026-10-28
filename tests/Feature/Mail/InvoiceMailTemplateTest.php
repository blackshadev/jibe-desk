<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Domain\Billing\Invoices\InvoiceMailLine;
use App\Domain\SharedKernel\CompoundPrice;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Markdown;
use Tests\FeatureTestCase;

final class InvoiceMailTemplateTest extends FeatureTestCase
{
    public function test_it_renders_the_shared_transfer_instructions(): void
    {
        $html = $this->render();

        static::assertStringContainsString('NL91ABNA0417164300', $html);
        static::assertStringContainsString('Watersportvereniging Almere Centraal', $html);
        static::assertStringContainsString('factuurnummer', $html);
        static::assertStringContainsString('€ 100,00', $html);
    }

    public function test_it_renders_the_refund_iban_for_a_credit_invoice(): void
    {
        $html = $this->render([
            'total' => new CompoundPrice(-100.0, -21.0),
            'recipientIban' => 'NL12ABCD1234567890',
        ]);

        static::assertStringContainsString('NL12ABCD1234567890', $html);
        static::assertStringContainsString('terug te betalen bedrag', $html);
    }

    public function test_it_renders_a_fallback_for_a_credit_invoice_without_an_iban(): void
    {
        $html = $this->render([
            'total' => new CompoundPrice(-100.0, -21.0),
            'recipientIban' => '',
        ]);

        static::assertStringContainsString('nemen hiervoor contact met u op', $html);
        static::assertStringNotContainsString('rekeningnummer', $html);
    }

    /** @param array<string, mixed> $overrides */
    private function render(array $overrides = []): string
    {
        return $this->app
            ->make(Markdown::class)
            ->render('mail.invoice', [
                'memberName' => 'Jan de Vries',
                'address' => 'Kerkstraat 12, 1111 AA Amsterdam',
                'email' => 'jan@example.com',
                'recipientIban' => 'NL12ABCD1234567890',
                'invoiceNumber' => 'I-20261001',
                'invoiceDate' => CarbonImmutable::parse('2026-06-01'),
                'total' => new CompoundPrice(100.0, 21.0),
                'lines' => [
                    new InvoiceMailLine(
                        description: 'Lidmaatschap 2026',
                        quantity: 1.0,
                        price: new CompoundPrice(100.0, 21.0),
                        subTotal: new CompoundPrice(100.0, 21.0),
                    ),
                ],
                'sepaTransferDate' => null,
                'creditorIban' => 'NL91ABNA0417164300',
                'creditorAccountName' => 'Watersportvereniging Almere Centraal',
                ...$overrides,
            ])
            ->toHtml();
    }
}
