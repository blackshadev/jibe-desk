<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\StorageSpaceRental;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyStorageRentalsWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 22;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    #[Override]
    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_storage_rentals'))
            ->query(
                static fn (): Builder => StorageSpaceRental::query()
                    ->whereIn('member_id', auth()->user()?->member?->visibleMemberIds() ?? [])
                    ->whereNowOrPast('start_date')
                    ->where(
                        static fn (Builder $query): Builder => $query
                            ->whereNull('end_date')
                            ->orWhereFuture('end_date'),
                    )
                    ->with('storageSpace.location')
                    ->orderBy('end_date'),
            )
            ->columns([
                TextColumn::make('storageSpace.location.name')
                    ->label(__('labels.location')),
                TextColumn::make('storageSpace.number')
                    ->label(__('labels.space_number')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.rented_until'))
                    ->date()
                    ->placeholder(__('labels.no_end_date')),
            ])
            ->emptyStateHeading(__('labels.no_storage_rentals'))
            ->paginated(false);
    }
}
