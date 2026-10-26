<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Tables;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\MemberId;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Filament\Admin\Utils\ResourceRouteHelper;
use App\Models\Member;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

final class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name')),
                TextColumn::make('membership.name')
                    ->label(__('labels.membership')),
                TextColumn::make('expiration_status')
                    ->label(__('labels.expiration_status'))
                    ->badge()
                    ->state(static fn (Member $record): ?MandateExpirationStatus => $record->paymentInformation?->expiration_status)
                    ->formatStateUsing(static fn (?MandateExpirationStatus $state): string => $state === null
                        ? __('labels.no_active_mandate')
                        : __('labels.expiration_statuses.' . $state->value))
                    ->color(static fn (?MandateExpirationStatus $state): string => match ($state) {
                        MandateExpirationStatus::Active => 'success',
                        MandateExpirationStatus::Expired => 'warning',
                        MandateExpirationStatus::Revoked, null => 'gray',
                    }),
                TextColumn::make('user.email')
                    ->label(__('labels.account'))
                    ->placeholder(__('labels.no_account'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stopped_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(ResourceRouteHelper::viewOrEdit(MemberResource::class))
            ->filters([
                SelectFilter::make('membership')
                    ->label(__('labels.membership'))
                    ->relationship('membership', 'name')
                    ->preload(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('acceptMembers')
                        ->label(__('labels.accept_members'))
                        ->icon(Heroicon::CheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(__('labels.accept_members_description'))
                        ->deselectRecordsAfterCompletion()
                        ->action(static function (Collection $records, AcceptMemberService $service): void {
                            foreach ($records as $record) {
                                $service->accept(MemberId::create($record->id));
                            }
                        })
                        ->successNotificationTitle(__('notifications.members_accepted')),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->searchable(['first_name', 'last_name', 'infix_name']);
    }
}
