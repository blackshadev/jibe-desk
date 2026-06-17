<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OutgoingEmails;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\OutgoingEmails\Pages\ListOutgoingEmails;
use App\Filament\Admin\Resources\OutgoingEmails\Tables\OutgoingEmailsTable;
use App\Models\OutgoingEmail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class OutgoingEmailResource extends Resource
{
    protected static ?string $model = OutgoingEmail::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Technical;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static bool $isGloballySearchable = false;

    #[Override]
    public static function table(Table $table): Table
    {
        return OutgoingEmailsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.outgoing_emails');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.outgoing_email');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListOutgoingEmails::route('/'),
        ];
    }
}
