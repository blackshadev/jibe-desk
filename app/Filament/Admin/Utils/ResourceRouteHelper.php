<?php

declare(strict_types=1);

namespace App\Filament\Admin\Utils;

use Closure;
use Filament\Resources\Resource;
use Gate;
use Illuminate\Database\Eloquent\Model;

final class ResourceRouteHelper
{
    /**
     * @param class-string<Resource> $resource
     * @return Closure(Model $record): string
     */
    public static function viewOrEdit(string $resource): Closure
    {
        return static fn (Model $record) => $resource::getUrl(Gate::allows('update', $record) ? 'edit' : 'view', ['record' => $record]);
    }

    /**
     * @param class-string<Resource> $resource
     * @return Closure(Model $record): string
     */
    public static function view(string $resource): Closure
    {
        return static fn (Model $record) => $resource::getUrl('view', ['record' => $record]);
    }

    /**
     * @param class-string<Resource> $resource
     */
    public static function viewOrEditFor(string $resource, Model $record): string
    {
        return $resource::getUrl(Gate::allows('update', $record) ? 'edit' : 'view', ['record' => $record]);
    }
}
