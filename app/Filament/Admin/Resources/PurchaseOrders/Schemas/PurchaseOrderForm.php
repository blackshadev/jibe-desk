<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Schemas;

use App\Filament\Admin\Labels\PurchaseOrderStatusLabels;
use App\Formatters\PriceFormatter;
use App\Models\CostCenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

final class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.purchase_order_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('creditor_name')
                            ->label(__('labels.creditor_name'))
                            ->required(),
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->required(),
                        TextInput::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull()
                            ->required(),
                        Select::make('status')
                            ->label(__('labels.status'))
                            ->options(PurchaseOrderStatusLabels::options())
                            ->disabled(),
                        FileUpload::make('image_path')
                            ->label(__('labels.image'))
                            ->image()
                            ->imagePreviewHeight('250')
                            ->directory('purchase-orders')
                            ->disk('local')
                            ->visibility('private')
                            ->previewable()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('labels.purchase_order_lines'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->collapsed()
                            ->itemLabel(static fn (array $state) => $state['description'] === ''
                                ? '(leeg)'
                                : sprintf('%s %s', $state['description'], PriceFormatter::format((float) $state['price'])))
                            ->columns(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('labels.description'))
                                    ->columnSpanFull()
                                    ->live()
                                    ->required(),
                                TextInput::make('price')
                                    ->label(__('labels.price'))
                                    ->prefix('€')
                                    ->live(true)
                                    ->afterStateUpdated(static function (?float $state, ?float $old, Get $get, Set $set) {
                                        $vat = $get('price_vat');
                                        if ($state === null) {
                                            return;
                                        }

                                        $delta = 0.001;
                                        if ($vat === null || abs($vat - round($old * 0.21, 2)) < $delta) {
                                            $set('price_vat', round($state * 0.21, 2));
                                        }
                                    })
                                    ->required(),
                                TextInput::make('price_vat')
                                    ->label(__('labels.price_vat'))
                                    ->prefix('€')
                                    ->required(),
                                Select::make('cost_center_id')
                                    ->label(__('labels.cost_center'))
                                    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
