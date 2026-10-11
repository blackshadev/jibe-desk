<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListMembers extends ListRecords
{
    #[Override]
    protected static string $resource = MemberResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'active' => Tab::make(__('labels.active'))
                ->badge(Member::query()->active()->count())
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->active(),
                ),
            'new' => Tab::make(__('labels.new'))
                ->badge(Member::query()->pending()->count())
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->pending(),
                ),
            'inactive' => Tab::make(__('labels.inactive'))
                ->badge(Member::query()->stopped()->count())
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->stopped(),
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
