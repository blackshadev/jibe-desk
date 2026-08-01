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
    #[Override]
    protected static ?string $model = OutgoingEmail::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Technical;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    #[Override]
    protected static ?string $recordTitleAttribute = 'subject';

    #[Override]
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
