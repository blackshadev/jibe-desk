# Invoice Batch Generation Progress UI

**Date:** Tue Oct 06 2026

## Overview

When an invoice batch is generated, the work runs asynchronously on the queue (`InvoiceBatchGeneratorImpl` dispatches one `GenerateInvoice` job per billable member). Today the batch edit page always renders the `BatchStatsOverview` widget, even while invoices are still being written, so an admin cannot tell that generation is still running.

This plan adds a generation-progress widget. While a batch is being filled it shows a progress bar ("X of Y invoices added") plus the elapsed duration; once generation completes it swaps back to the existing `BatchStatsOverview` widget.

## Current situation

- `app/Domain/Invoices/InvoiceBatchGeneratorImpl.php` orchestrates generation:
  1. `InvoiceBatchService::createBatch()` creates an empty `Open` batch.
  2. `InvoiceBatchService::attachBatchMonth()` attaches pre-existing open invoices from the previous month.
  3. `BillableItemsViewRepository::listBillableMembers()` returns the members to generate invoices for.
  4. It dispatches a `JobBatch` of `GenerateInvoice` jobs with a trailing `SendInvoiceBatchCreatedEmail` job (`->after(...)`).
- `app/Domain/Invoices/InvoiceBatchGeneratorImpl.php` returns early (no jobs dispatched) when there are no billable members.
- The batch edit page `app/Filament/Admin/Resources/InvoiceBatches/Pages/EditInvoiceBatch.php` unconditionally renders `BatchStatsOverview` as the only header widget.
- `app/Models/InvoiceBatch.php` already exposes `invoice_count`, `open_invoice_count`, `total`, and `open_total` accessors. There are no generation timestamps.
- The batch `status` enum (`InvoiceBatchStatus`) stays `Open` during generation, so status cannot be used to detect "still filling".
- Generation is triggered from two places: `app/Console/Commands/GenerateInvoiceBatchCommand.php` and `app/Filament/Admin/Resources/InvoiceBatches/Actions/GenerateInvoiceBatchAction.php`. Both funnel through `InvoiceBatchGeneratorImpl::generate()`.

## Planned changes

1. **Migration** — add `generation_started_at`, `generation_finished_at`, and `generation_expected_invoices` to `invoice_batches`.
2. **Model** — cast/fill the new columns and add an `isGenerating()` helper.
3. **Repository** — add `markGenerationStarted()` / `markGenerationFinished()`; make `addOpenInvoicesFromBatchMonth()` return the attached count.
4. **Service** — add `startGeneration()` / `finishGeneration()`; make `attachBatchMonth()` return the attached count.
5. **Generator** — record the expected total, mark generation started, mark it finished synchronously when there is no work, and add a `FinishInvoiceBatchGeneration` job to the job chain for the async path.
6. **New job** — `FinishInvoiceBatchGeneration` marks a batch's generation as finished.
7. **Filament page** — conditionally render the progress widget while generating, otherwise `BatchStatsOverview`.
8. **New widget + view** — `InvoiceBatchGenerationProgress` with a Blade view (progress bar + duration + polling + reload-on-completion).
9. **i18n** — add Dutch labels.
10. **Factory** — add helpful `generating()` / `generationFinished()` states.
11. **Tests** — update and add unit/feature tests across the touched layers.

### Semantics of the progress denominator

`generation_expected_invoices` is the total number of invoices the batch will contain once generation finishes: the count of invoices attached by `attachBatchMonth()` plus the count of billable members (each of which produces at most one invoice). The progress bar therefore reads "current `invoice_count` of `generation_expected_invoices`".

"Generation is finished" is defined as `generation_started_at !== null && generation_finished_at !== null`. Batches created manually via the `CreateInvoiceBatch` page never call the generator, so both timestamps stay `null`; such batches simply show `BatchStatsOverview`.

---

## Change: Migration

Create a new migration (via `./Taskfile artisan make:migration add_generation_progress_to_invoice_batches_table --table=invoice_batches`). It must add three nullable columns:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('generation_finished_at')->nullable();
            $table->unsignedInteger('generation_expected_invoices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_batches', static function (Blueprint $table): void {
            $table->dropColumn(['generation_started_at', 'generation_finished_at', 'generation_expected_invoices']);
        });
    }
};
```

---

## Change: Model `app/Models/InvoiceBatch.php`

Add the columns to fillable, casts, and PHPDoc, and add an `isGenerating()` helper.

```php
use Carbon\CarbonInterface;

/**
 * @property InvoiceBatchStatus $status
 * @property DateTimeInterface $invoice_date
 * @property DateTimeInterface $sepa_transfer_date
 * @property CarbonInterface|null $generation_started_at
 * @property CarbonInterface|null $generation_finished_at
 * @property int|null $generation_expected_invoices
 */
#[Fillable([
    'invoice_date',
    'sepa_transfer_date',
    'status',
    'generation_started_at',
    'generation_finished_at',
    'generation_expected_invoices',
])]
final class InvoiceBatch extends Model
{
    use HasFactory;

    #[Override]
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'sepa_transfer_date' => 'date',
            'status' => InvoiceBatchStatus::class,
            'generation_started_at' => 'datetime',
            'generation_finished_at' => 'datetime',
            'generation_expected_invoices' => 'integer',
        ];
    }

    public function isGenerating(): bool
    {
        return $this->generation_started_at !== null && $this->generation_finished_at === null;
    }

    // ... existing accessors unchanged ...
}
```

The two timestamps must not be added to `InvoiceBatchForm` or `InvoiceBatchesTable`, so they stay invisible in the normal UI as required.

---

## Change: Repository interface `app/Domain/Invoices/InvoiceBatchRepository.php`

Change `addOpenInvoicesFromBatchMonth()` to return the number of attached invoices, and add two new methods:

```php
public function addOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId): int;

public function markGenerationStarted(InvoiceBatchId $batchId, int $expectedInvoices): void;

public function markGenerationFinished(InvoiceBatchId $batchId): void;
```

---

## Change: Repository implementation `app/Infrastructure/Invoices/InvoiceBatchRepositoryDb.php`

`addOpenInvoicesFromBatchMonth()` already uses `update()`, which returns the affected-row count — return it. Add the two new methods:

```php
#[Override]
public function addOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId): int
{
    $month = new CarbonImmutable(InvoiceBatch::findOrFail($batchId->value)->invoice_date);

    return Invoice::query()
        ->whereNull('invoice_batch_id')
        ->where('status', InvoiceStatus::Open)
        ->whereBetween('date', [
            $month->subMonth()->startOfMonth(),
            $month->endOfMonth(),
        ])
        ->update(['invoice_batch_id' => $batchId->value]);
}

#[Override]
public function markGenerationStarted(InvoiceBatchId $batchId, int $expectedInvoices): void
{
    InvoiceBatch::query()
        ->where('id', $batchId->value)
        ->update([
            'generation_started_at' => now(),
            'generation_expected_invoices' => $expectedInvoices,
        ]);
}

#[Override]
public function markGenerationFinished(InvoiceBatchId $batchId): void
{
    InvoiceBatch::query()
        ->where('id', $batchId->value)
        ->update(['generation_finished_at' => now()]);
}
```

---

## Change: Service interface `app/Domain/Invoices/InvoiceBatchService.php`

Change `attachBatchMonth()` return type and add the two generation methods:

```php
public function attachBatchMonth(InvoiceBatchId $batchId): int;

public function startGeneration(InvoiceBatchId $batchId, int $expectedInvoices): void;

public function finishGeneration(InvoiceBatchId $batchId): void;
```

---

## Change: Service implementation `app/Domain/Invoices/InvoiceBatchServiceImpl.php`

```php
#[Override]
public function attachBatchMonth(InvoiceBatchId $batchId): int
{
    return $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
}

#[Override]
public function startGeneration(InvoiceBatchId $batchId, int $expectedInvoices): void
{
    $this->batchRepository->markGenerationStarted($batchId, $expectedInvoices);
}

#[Override]
public function finishGeneration(InvoiceBatchId $batchId): void
{
    $this->batchRepository->markGenerationFinished($batchId);
}
```

---

## Change: New job `app/Domain/Invoices/Jobs/FinishInvoiceBatchGeneration.php`

Mirrors the existing `SendInvoiceBatchCreatedEmail` job shape. It runs as the chain job right after the `GenerateInvoice` batch completes and marks the batch as finished.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchService;
use App\Domain\Jobs\BaseJob;

final class FinishInvoiceBatchGeneration extends BaseJob
{
    public function __construct(
        private readonly InvoiceBatchId $invoiceBatchId,
    ) {}

    public function handle(InvoiceBatchService $batchService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $batchService->finishGeneration($this->invoiceBatchId);
    }
}
```

---

## Change: Generator `app/Domain/Invoices/InvoiceBatchGeneratorImpl.php`

Record the expected total, mark generation started, finish synchronously when there are no billable members, and insert `FinishInvoiceBatchGeneration` before the email job in the chain.

```php
use App\Domain\Invoices\Jobs\FinishInvoiceBatchGeneration;

#[Override]
public function generate(InvoiceBatch $invoiceBatch): InvoiceBatchId
{
    $batchId = $this->batchService->createBatch($invoiceBatch->invoiceDate, $invoiceBatch->sepaTransferDate);
    $attachedCount = $this->batchService->attachBatchMonth($batchId);

    $billableMembers = $this->billableItemRepository->listBillableMembers($invoiceBatch->invoiceDate);

    $this->batchService->startGeneration($batchId, $attachedCount + count($billableMembers->ids));

    $batchName = sprintf('invoice-batch-%s-%s', $invoiceBatch->invoiceDate->format('Y-m-d'), $batchId->value);

    if ($billableMembers->ids === []) {
        $this->batchService->finishGeneration($batchId);

        return $batchId;
    }

    $jobs = array_map(
        static fn (MemberId $id) => new GenerateInvoice(
            new InvoiceTarget(
                memberId: $id,
                invoiceDate: $invoiceBatch->invoiceDate,
                batchId: $batchId,
            ),
        ),
        $billableMembers->ids,
    );

    Assert::isList($jobs);
    $batch = (new JobBatch($batchName, $jobs))
        ->after(new FinishInvoiceBatchGeneration($batchId))
        ->after(new SendInvoiceBatchCreatedEmail($batchId));

    $this->dispatcher->dispatch($batch);

    return $batchId;
}
```

---

## Change: Page `app/Filament/Admin/Resources/InvoiceBatches/Pages/EditInvoiceBatch.php`

Swap the header widget based on the record's generation state:

```php
use App\Filament\Admin\Resources\InvoiceBatches\Widgets\InvoiceBatchGenerationProgress;

#[Override]
protected function getHeaderWidgets(): array
{
    return [
        $this->getRecord()->isGenerating()
            ? InvoiceBatchGenerationProgress::make(['record' => $this->getRecord()])
            : BatchStatsOverview::make(['record' => $this->getRecord()]),
    ];
}
```

`getHeaderWidgets()` is evaluated on page render. The progress widget triggers a full page reload once generation completes (see below), which re-evaluates this method and renders `BatchStatsOverview` instead.

---

## Change: New widget `app/Filament/Admin/Resources/InvoiceBatches/Widgets/InvoiceBatchGenerationProgress.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Widgets;

use App\Models\InvoiceBatch;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Override;

final class InvoiceBatchGenerationProgress extends Widget
{
    use CanPoll;

    public ?InvoiceBatch $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.admin.resources.invoice-batches.widgets.invoice-batch-generation-progress';

    #[Override]
    protected function getViewData(): array
    {
        if ($this->record === null || !$this->record->isGenerating()) {
            $this->js('window.location.reload()');

            return [];
        }

        $total = $this->record->generation_expected_invoices ?? 0;
        $added = $this->record->invoice_count;


        return [
            'addedCount' => $added,
            'totalCount' => $total,
            'progressPercent' => $total > 0 ? clamp(round($added / $total * 100), 0, 100) : 0,
            'duration' => $this->record->generation_started_at?->diffForHumans(now(), true),
        ];
    }
}
```

Notes:

- `CanPoll` provides the default `5s` `getPollingInterval()`, consumed by the view's `wire:poll`.
- `record` is a public Livewire property holding an Eloquent model, so Livewire re-queries it from the database on every poll; `isGenerating()` and `invoice_count` therefore reflect fresh state.
- When a poll observes `isGenerating()` become `false`, it queues `window.location.reload()` so the page re-renders and swaps the widget to `BatchStatsOverview`.

---

## Change: New view `resources/views/filament/admin/resources/invoice-batches/widgets/invoice-batch-generation-progress.blade.php`

```blade
@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class(['fi-wi-invoice-batch-generation-progress'])
    "
>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ __('labels.generation_in_progress') }}
        </h3>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ __('labels.invoices_added', ['added' => $addedCount, 'total' => $totalCount]) }}
            <span aria-hidden="true">&middot;</span>
            {{ __('labels.duration') }}: {{ $duration }}
        </p>

        <div
            class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            aria-valuenow="{{ $progressPercent }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progressPercent }}%"></div>
        </div>
    </div>
</x-filament-widgets::widget>
```

Exact Tailwind utility classes may be tuned to match the existing Filament theme; the structural contract (widget wrapper, heading, "X of Y", duration, progress bar) is what matters.

---

## Change: i18n `lang/nl/labels.php`

Add the following keys alongside the existing invoice-batch labels:

```php
'generation_in_progress' => 'Facturatieronde wordt gegenereerd',
'invoices_added' => ':added van :total facturen toegevoegd',
'duration' => 'Duur',
```

---

## Change: Factory `database/factories/InvoiceBatchFactory.php`

Add convenience states so tests can build generating / finished batches without hand-writing timestamps:

```php
public function generating(int $expectedInvoices = 10): self
{
    return $this->state([
        'generation_started_at' => Carbon::now(),
        'generation_expected_invoices' => $expectedInvoices,
        'generation_finished_at' => null,
    ]);
}

public function generationFinished(): self
{
    return $this->state([
        'generation_started_at' => Carbon::now()->subMinute(),
        'generation_finished_at' => Carbon::now(),
    ]);
}
```

---

## Change: Tests

### `tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php`

- Update `test_add_open_invoices_from_batch_month()` to assert the return value equals the number of attached invoices (2 in that fixture).
- Add `test_mark_generation_started()` and `test_mark_generation_finished()`:

```php
public function test_mark_generation_started(): void
{
    $batch = InvoiceBatch::factory()->create();

    $this->repository->markGenerationStarted(InvoiceBatchId::create($batch->id), 25);

    $this->assertDatabaseHas('invoice_batches', [
        'id' => $batch->id,
        'generation_expected_invoices' => 25,
    ]);

    static::assertNotNull($batch->fresh()->generation_started_at);
}

public function test_mark_generation_finished(): void
{
    $batch = InvoiceBatch::factory()->generating()->create();

    $this->repository->markGenerationFinished(InvoiceBatchId::create($batch->id));

    static::assertNotNull($batch->fresh()->generation_finished_at);
}
```

### `tests/Unit/Domain/Invoices/InvoiceBatchRepositoryExpectation.php`

Update `expectsAddOpenInvoicesFromBatchMonth()` to return a count and add expectations for the two new methods:

```php
public function expectsAddOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId, int $return): void
{
    $this->mock
        ->expects('addOpenInvoicesFromBatchMonth')
        ->with(equalTo($batchId))
        ->andReturn($return);
}

public function expectsMarkGenerationStarted(InvoiceBatchId $batchId, int $expectedInvoices): void
{
    $this->mock
        ->expects('markGenerationStarted')
        ->with(equalTo($batchId), equalTo($expectedInvoices));
}

public function expectsMarkGenerationFinished(InvoiceBatchId $batchId): void
{
    $this->mock
        ->expects('markGenerationFinished')
        ->with(equalTo($batchId));
}
```

### `tests/Unit/Domain/Invoices/InvoiceBatchServiceExpectation.php`

Update `expectsAttachBatchMonth()` to return a count and add the two generation expectations:

```php
public function expectsAttachBatchMonth(InvoiceBatchId $id, int $return): void
{
    $this->mock
        ->expects('attachBatchMonth')
        ->with(equalTo($id))
        ->andReturn($return);
}

public function expectsStartGeneration(InvoiceBatchId $id, int $expectedInvoices): void
{
    $this->mock
        ->expects('startGeneration')
        ->with(equalTo($id), equalTo($expectedInvoices));
}

public function expectsFinishGeneration(InvoiceBatchId $id): void
{
    $this->mock
        ->expects('finishGeneration')
        ->with(equalTo($id));
}
```

### `tests/Unit/Domain/Invoices/InvoiceBatchServiceImplTest.php` and `InvoiceBatchServiceTest.php`

Both files assert the same implementation. Update them to cover the changed return value and new methods:

- `test_attach_batch_month()`: `expectsAddOpenInvoicesFromBatchMonth($batchId, 3)` and `static::assertSame(3, $this->service->attachBatchMonth($batchId));`.
- Add `test_start_generation()`: `expectsMarkGenerationStarted($batchId, 25)` then `$this->service->startGeneration($batchId, 25);`.
- Add `test_finish_generation()`: `expectsMarkGenerationFinished($batchId)` then `$this->service->finishGeneration($batchId);`.

### `tests/Unit/Domain/Invoices/InvoiceBatchGeneratorImplTest.php`

Update both existing tests for the new service calls and the expanded job chain:

- `test_it_generates_one_invoice_per_billable_member()` — with 2 billable members and `attachBatchMonth` returning e.g. 3, expect `expectsStartGeneration($batchId, 5)` and a chain of `FinishInvoiceBatchGeneration` before `SendInvoiceBatchCreatedEmail`.
- `test_it_does_not_generate_invoices_when_no_billable_members_exist()` — expect `attachBatchMonth` returning 3, `expectsStartGeneration($batchId, 3)`, `expectsFinishGeneration($batchId)`, and `expectsNoDispatch()`.

```php
$this->batchService->expectsAttachBatchMonth($batchId, 3);
$this->batchService->expectsStartGeneration($batchId, 5);
$this->jobDispatcher->expectsDispatch(
    (new JobBatch(
        'invoice-batch-2026-05-25-9',
        [
            new GenerateInvoice(new InvoiceTarget(MemberId::create(1), $invoiceDate, $batchId)),
            new GenerateInvoice(new InvoiceTarget(MemberId::create(2), $invoiceDate, $batchId)),
        ],
    ))
        ->after(new FinishInvoiceBatchGeneration($batchId))
        ->after(new SendInvoiceBatchCreatedEmail($batchId)),
);
```

### New `tests/Unit/Domain/Invoices/Jobs/FinishInvoiceBatchGenerationTest.php`

Model it on `SendInvoiceBatchCreatedEmailTest`:

```php
final class FinishInvoiceBatchGenerationTest extends UnitTestCase
{
    private InvoiceBatchServiceExpectation $batchService;

    #[Override]
    protected function setup(): void
    {
        parent::setup();

        $this->batchService = InvoiceBatchServiceExpectation::create();
    }

    public function test_handle_marks_generation_finished(): void
    {
        $batchId = InvoiceBatchId::create(10);

        $this->batchService->expectsFinishGeneration($batchId);

        $job = new FinishInvoiceBatchGeneration($batchId);

        $job->handle($this->batchService->mock);
    }

    public function test_handle_does_nothing_when_batch_is_cancelled(): void
    {
        $batchId = InvoiceBatchId::create(10);

        $job = new FinishInvoiceBatchGeneration($batchId);
        $job->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($this->batchService->mock);

        $this->batchService->mock->shouldNotHaveReceived('finishGeneration');
    }
}
```

### `tests/Feature/Filament/InvoiceBatches/InvoiceBatchResourceTest.php`

Add model-behaviour and widget-selection tests:

```php
public function test_batch_reports_generating_while_started_but_not_finished(): void
{
    $batch = InvoiceBatch::factory()->generating()->create();

    static::assertTrue($batch->isGenerating());
}

public function test_batch_is_not_generating_when_finished(): void
{
    $batch = InvoiceBatch::factory()->generationFinished()->create();

    static::assertFalse($batch->isGenerating());
}

public function test_batch_is_not_generating_when_generation_never_started(): void
{
    $batch = InvoiceBatch::factory()->create();

    static::assertFalse($batch->isGenerating());
}
```

For the page widget swap, add a focused Livewire test asserting the rendered widget content (the translated heading text is the stable signal):

```php
use App\Filament\Admin\Resources\InvoiceBatches\Pages\EditInvoiceBatch;

public function test_edit_page_shows_progress_widget_while_generating(): void
{
    $this->withAuthorizedUser();

    $batch = InvoiceBatch::factory()->generating(12)->create();

    Livewire::test(EditInvoiceBatch::class, ['record' => $batch->getRouteKey()])
        ->assertSee(__('labels.generation_in_progress'));
}

public function test_edit_page_shows_stats_widget_when_generation_finished(): void
{
    $this->withAuthorizedUser();

    $batch = InvoiceBatch::factory()->generationFinished()->create();

    Livewire::test(EditInvoiceBatch::class, ['record' => $batch->getRouteKey()])
        ->assertDontSee(__('labels.generation_in_progress'))
        ->assertSee(__('labels.invoice_count'));
}
```

If `assertSee` on the translated heading proves fragile against the page's widget markup, the equivalent is `->assertSeeLivewire(InvoiceBatchGenerationProgress::class)` / `->assertDontSeeLivewire(...)` using the widget class name.

### Widget rendering test (optional but recommended)

Add `tests/Feature/Filament/InvoiceBatches/InvoiceBatchGenerationProgressTest.php` that mounts the widget directly with a generating record and asserts it renders the "X of Y" text and the computed percentage:

```php
Livewire::test(InvoiceBatchGenerationProgress::class, ['record' => $batch])
    ->assertSee(__('labels.invoices_added', ['added' => 4, 'total' => 12]))
    ->assertSee('style="width: 33%"');
```

---

## Verification

Run the affected tests after implementation:

```
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchGeneratorImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/Jobs/FinishInvoiceBatchGenerationTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Invoices/InvoiceBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Filament/InvoiceBatches/InvoiceBatchResourceTest.php
```

If a frontend change isn't reflected, the user may need `bun run build` / `bun run dev` / `composer run dev`.
