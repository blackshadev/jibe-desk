<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    public function getTabs(): array
    {
        return [
            'active' => Tab::make(__('labels.active')),
            'inactive' => Tab::make(__('labels.inactive'))
                ->modifyQueryUsing(static fn (Builder $query) => $query->onlyTrashed()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
