<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InvoicePolicyTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_a_settled_invoice_denies_every_status_ability(): void
    {
        $user = $this->withAuthorizedUser();
        $original = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Settled]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Settled,
            'credit_invoice_id' => $original->id,
        ]);

        $abilities = [
            'update',
            'createCredit',
            'markDeclined',
            'resendEmail',
            'reopen',
            'markPaid',
            'markPending',
            'attachBankTransaction',
            'delete',
        ];

        foreach ([$original, $credit] as $invoice) {
            foreach ($abilities as $ability) {
                static::assertFalse(
                    Gate::forUser($user)->allows($ability, $invoice),
                    "Ability {$ability} should be denied on a settled invoice.",
                );
            }
        }
    }

    public function test_a_settled_invoice_can_still_be_viewed(): void
    {
        $user = $this->withAuthorizedUser();
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Settled]);

        static::assertTrue(Gate::forUser($user)->allows('view', $invoice));
    }
}
