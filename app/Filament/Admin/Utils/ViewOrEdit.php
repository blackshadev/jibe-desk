<?php

declare(strict_types=1);

namespace App\Filament\Admin\Utils;

use Closure;
use Filament\Resources\Resource;
use Gate;
use Illuminate\Database\Eloquent\Model;

final class ViewOrEdit
{
    /**
     * @param class-string<Resource> $resource
     * @return Closure(Model $record): string
     */
    public static function route(string $resource): Closure
    {
        return static fn (Model $record) => $resource::getUrl(Gate::allows('update', $record) ? 'edit' : 'view', ['record' => $record]);
    }
}
