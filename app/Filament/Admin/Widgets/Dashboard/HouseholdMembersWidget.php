<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class HouseholdMembersWidget extends TableWidget
{
    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.household_members'))
            ->query(
                static fn (): Builder => Member::query()
                    ->whereIn('id', auth()->user()?->member?->visibleMemberIds() ?? []),
            )
            ->columns([
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('membership.name')->label(__('labels.membership')),
                TextColumn::make('age')->label(__('labels.age')),
            ])
            ->recordUrl(
                static fn (Member $record): string => MemberResource::getUrl('view', ['record' => $record]),
            );
    }
}
