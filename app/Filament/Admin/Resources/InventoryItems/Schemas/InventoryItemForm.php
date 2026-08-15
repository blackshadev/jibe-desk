<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Schemas;

use App\Models\PurchaseOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

final class InventoryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label(__('labels.purchase_order'))
                            ->relationship('purchaseOrder', 'description')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(static function (?int $state, Set $set): void {
                                if ($state === null) {
                                    return;
                                }

                                $po = PurchaseOrder::find($state);
                                if ($po === null) {
                                    return;
                                }

                                $set('description', $po->description);
                                $set('date', $po->date->format('Y-m-d'));
                                $set('amount', $po->total->price);

                                if ($po->image_path !== null) {
                                    $sourcePath = $po->image_path;
                                    $filename = pathinfo($sourcePath, PATHINFO_BASENAME);
                                    $newPath = 'inventory-items/' . $filename;

                                    if (Storage::disk('local')->exists($sourcePath)) {
                                        Storage::disk('local')->copy($sourcePath, $newPath);
                                        $set('receipt_path', $newPath);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull(),
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->required(),
                        Select::make('inventory_category_id')
                            ->label(__('labels.category'))
                            ->relationship('inventoryCategory', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('labels.purchase_price'))
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        TextInput::make('write_off_period_years')
                            ->label(__('labels.write_off_period_years'))
                            ->numeric()
                            ->required(),
                        FileUpload::make('receipt_path')
                            ->label(__('labels.receipt'))
                            ->image()
                            ->imagePreviewHeight('250')
                            ->directory('inventory-items')
                            ->disk('local')
                            ->visibility('private')
                            ->previewable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
