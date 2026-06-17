<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OutgoingEmails\Pages;

use App\Filament\Admin\Resources\OutgoingEmails\OutgoingEmailResource;
use Filament\Resources\Pages\ListRecords;

final class ListOutgoingEmails extends ListRecords
{
    protected static string $resource = OutgoingEmailResource::class;
}
