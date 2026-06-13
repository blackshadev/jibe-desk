<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Pages;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'active' => Tabs\Tab::make(__('labels.active'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->active(),
                ),
            'inactive' => Tabs\Tab::make(__('labels.inactive'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->inactive(),
                ),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
