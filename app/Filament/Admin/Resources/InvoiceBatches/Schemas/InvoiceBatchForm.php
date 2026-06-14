<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Schemas;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Models\InvoiceBatch;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

final class InvoiceBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.invoice_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('invoice_date')
                            ->label(__('labels.invoice_date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->default(now()->format('d-m-Y'))
                            ->required(),
                        Checkbox::make('attach_invoices')
                            ->label(__('labels.attach_invoices'))
                            ->columnSpanFull()
                            ->visibleOn(Operation::Create)
                            ->default(false),
                    ]),
            ])
            ->disabled(static fn (?InvoiceBatch $record) => $record !== null && $record->status !== InvoiceBatchStatus::Open);
    }
}
