<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Schemas;

use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

final class SepaBatchForm
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
                        DatePicker::make('sepa_transfer_date')
                            ->label(__('labels.sepa_transfer_date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->default(now()->addDays(14)->format('d-m-Y'))
                            ->afterOrEqual('invoice_date')
                            ->required(),
                        Checkbox::make('attach_invoices')
                            ->label(__('labels.attach_invoices'))
                            ->columnSpanFull()
                            ->visibleOn(Operation::Create)
                            ->default(true),
                        Checkbox::make('attach_purchase_orders')
                            ->label(__('labels.attach_purchase_orders'))
                            ->columnSpanFull()
                            ->visibleOn(Operation::Create)
                            ->default(true),
                    ]),
            ])
            ->disabled(static fn (?SepaBatch $record) => $record !== null && $record->status !== SepaBatchStatus::Open);
    }
}
