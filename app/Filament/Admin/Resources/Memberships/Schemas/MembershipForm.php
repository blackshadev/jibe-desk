<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Schemas;

use App\Filament\Admin\Sections\BillableItemSection;
use App\Models\Membership;
use App\Rules\UniqueDefaultMembership;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MembershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),
                        Toggle::make('is_default')
                            ->rule(static fn (?Membership $record) => new UniqueDefaultMembership($record?->id))
                            ->label(__('labels.default_membership')),
                    ]),
                BillableItemSection::make(
                    relationship: 'adultBillableItem',
                    label: __('labels.billing_adults'),
                ),
                BillableItemSection::make(
                    relationship: 'kidsBillableItem',
                    label: __('labels.billing_kids'),
                ),
            ]);
    }
}
