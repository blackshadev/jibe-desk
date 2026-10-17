<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Pages;

use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Filament\Admin\Resources\SepaBatches\Actions\GenerateSepaBatchAction;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListSepaBatches extends ListRecords
{
    #[Override]
    protected static string $resource = SepaBatchResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.batch_status.open'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', SepaBatchStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.batch_status.pending'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', SepaBatchStatus::Pending),
                ),
            'completed' => Tabs\Tab::make(__('labels.batch_status.completed'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', SepaBatchStatus::Completed),
                ),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GenerateSepaBatchAction::make(),
        ];
    }
}
