<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\BookkeepingRecords;

use App\Filament\Admin\Resources\BookkeepingRecords\Pages\ViewBookkeepingRecord;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\Member;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class ViewBookkeepingRecordTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_view_page_renders(): void
    {
        $this->withAuthorizedUser();
        $record = BookkeepingRecord::factory()->createQuietly();

        Livewire::test(ViewBookkeepingRecord::class, ['record' => $record->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_view_page_displays_invoice_reference(): void
    {
        $this->withAuthorizedUser();
        $invoice = Invoice::factory()
            ->forMember(Member::factory()->createQuietly())
            ->withLines(1)
            ->createQuietly();
        $record = BookkeepingRecord::factory()->createQuietly();
        $record->reference()->associate($invoice);
        $record->save();

        Livewire::test(ViewBookkeepingRecord::class, ['record' => $record->getRouteKey()])
            ->assertSuccessful()
            ->assertSee($invoice->display_name);
    }

    public function test_view_page_displays_banking_transaction(): void
    {
        $this->withAuthorizedUser();
        $bankingTransaction = BankingTransaction::factory()->createQuietly();
        $record = BookkeepingRecord::factory()
            ->createQuietly(['banking_transaction_id' => $bankingTransaction->id]);

        Livewire::test(ViewBookkeepingRecord::class, ['record' => $record->getRouteKey()])
            ->assertSuccessful()
            ->assertSee($bankingTransaction->description)
            ->assertSee($bankingTransaction->date->format('Y-m-d'));
    }

    public function test_view_page_hides_reference_when_null(): void
    {
        $this->withAuthorizedUser();
        $record = BookkeepingRecord::factory()->createQuietly();

        Livewire::test(ViewBookkeepingRecord::class, ['record' => $record->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee(__('labels.reference'));
    }

    public function test_view_page_hides_banking_transaction_when_null(): void
    {
        $this->withAuthorizedUser();
        $record = BookkeepingRecord::factory()->createQuietly();

        Livewire::test(ViewBookkeepingRecord::class, ['record' => $record->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee(__('labels.banking_transaction'));
    }
}
