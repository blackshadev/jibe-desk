<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OutgoingEmails\Tables;

use App\Domain\Mail\OutgoingEmailStatus;
use App\Filament\Admin\Labels\OutgoingEmailStatusLabels;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class OutgoingEmailsTable
{
    public static function configure(Table $table): Table
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_at')
                    ->label(__('labels.sent_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('labels.status'))
                    ->options(OutgoingEmailStatus::class),
            ]);
    }
}
