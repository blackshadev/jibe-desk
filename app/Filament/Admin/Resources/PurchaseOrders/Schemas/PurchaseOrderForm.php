<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Schemas;

use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Labels\PurchaseOrderStatusLabels;
use App\Filament\Admin\Resources\PurchaseOrders\Helpers\MemberCreditorPrefill;
use App\Models\CostCenter;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Intervention\Validation\Rules\Iban;

final class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns()
            ->components([
                Section::make(__('labels.purchase_order_information'))
                    ->schema([
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
                        Textarea::make('declined_reason')
                            ->label(__('labels.declined_reason'))
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated()
                            ->visible(static fn (Get $get): bool => $get('status') === PurchaseOrderStatus::Declined->value),
                        Textarea::make('notes')
                            ->label(__('labels.notes'))
                            ->rows(5)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label(__('labels.image'))
                            ->image()
                            ->imagePreviewHeight('250')
                            ->directory('purchase-orders')
                            ->disk('local')
                            ->visibility('private')
                            ->previewable()
                            ->required(static fn (string $operation): bool => $operation === Operation::Create->value)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('labels.creditor_information'))
                    ->disabled(static fn (): bool => auth()->user()->cannot('update_purchase_orders'))
                    ->schema([
                        Select::make('member_id')
                            ->label(__('labels.member'))
                            ->options(
                                static fn (): array => Member::query()
                                    ->get()
                                    ->mapWithKeys(static fn (Member $member): array => [$member->id => $member->name])
                                    ->all(),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(static function (?string $state, Set $set): void {
                                if ($state === null) {
                                    return;
                                }

                                $member = Member::query()->find($state);
                                if ($member === null) {
                                    return;
                                }

                                foreach (MemberCreditorPrefill::for($member) as $field => $value) {
                                    $set($field, $value);
                                }
                            }),
                        TextInput::make('creditor_name')
                            ->label(__('labels.name'))
                            ->disabled(static fn (Get $get): bool => filled($get('member_id')))
                            ->dehydrated(),
                        TextInput::make('creditor_iban')
                            ->label(__('labels.iban'))
                            ->rule(new Iban())
                            ->disabled(static fn (Get $get): bool => filled($get('member_id')))
                            ->dehydrated(),
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
                                    ->visible(static fn () => auth()->user()?->can('viewAny', CostCenter::class))
                                    ->label(__('labels.cost_center'))
                                    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->helperText(__('labels.cost_center_optional_hint')),
                            ]),
                    ]),
            ]);
    }
}
