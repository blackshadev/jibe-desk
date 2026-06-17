<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Labels\OutgoingEmailStatusLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class OutgoingEmailsRelationManager extends RelationManager
{
    protected static string $relationship = 'outgoingEmails';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mailable_class')
                    ->label(__('labels.mailable_type'))
                    ->formatStateUsing(static fn (string $state): string => class_basename($state)),
                TextColumn::make('recipient_email')
                    ->label(__('labels.recipient_email'))
                    ->searchable(),
                TextColumn::make('recipient_name')
                    ->label(__('labels.recipient_name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject')
                    ->label(__('labels.subject'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->badge()
                    ->formatStateUsing(OutgoingEmailStatusLabels::label(...))
                    ->color(OutgoingEmailStatusLabels::color(...)),
                TextColumn::make('batch_id')
                    ->label(__('labels.batch_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label(__('labels.error_message'))
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('queued_at')
                    ->label(__('labels.queued_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('labels.sent_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sent_at', 'desc');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.outgoing_emails');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.outgoing_email'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.outgoing_emails'));
    }
}
