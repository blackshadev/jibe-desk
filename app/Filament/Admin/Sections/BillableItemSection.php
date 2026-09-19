<?php

declare(strict_types=1);

namespace App\Filament\Admin\Sections;

use App\Filament\Admin\Labels\BillMonthLabels;
use App\Filament\Admin\Labels\BillPeriodLabels;
use App\Models\CostCenter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

final class BillableItemSection
{
    private const VAT_RATE = 0.21;

    public static function make(
        string $relationship = 'billableItem',
        ?string $label = null,
    ): Section {
        return Section::make($label ?? __('labels.billing'))
            ->relationship($relationship)
            ->schema([
                TextInput::make('description')
                    ->label(__('labels.description'))
                    ->required(),
                TextInput::make('price')
                    ->label(__('labels.price'))
                    ->required(),
                Select::make('bill_period')
                    ->label(__('labels.bill_period'))
                    ->options(BillPeriodLabels::options())
                    ->required(),
                Select::make('bill_month')
                    ->label(__('labels.bill_month'))
                    ->options(BillMonthLabels::options())
                    ->default(1)
                    ->required(),
                Select::make('cost_center_id')
                    ->label(__('labels.cost_center'))
                    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => [
                ...$data,
                'vat' => $data['price'] * self::VAT_RATE,
            ])->mutateRelationshipDataBeforeSaveUsing(static fn (array $data): array => [
                ...$data,
                'vat' => $data['price'] * self::VAT_RATE,
            ]);
    }
}
