<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Tables;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Filament\Admin\Utils\ResourceRouteHelper;
use App\Models\Member;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->state(static fn (Member $record): ?MandateExpirationStatus => $record->currentPaymentInformation?->expiration_status)
                    ->formatStateUsing(static fn (?MandateExpirationStatus $state): string => $state === null
                        ? __('labels.no_mandate')
                        : __('labels.expiration_statuses.' . $state->value))
                    ->color(static fn (?MandateExpirationStatus $state): string => match ($state) {
                        MandateExpirationStatus::Active => 'success',
                        MandateExpirationStatus::Expired => 'warning',
                        MandateExpirationStatus::Revoked, null => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->label(__('labels.payment_method'))
                    ->badge()
                    ->formatStateUsing(static fn (PaymentMethod $state): string => __('labels.payment_methods.' . $state->value))
                    ->color(static fn (PaymentMethod $state): string => match ($state) {
                        PaymentMethod::DirectDebit => 'info',
                        PaymentMethod::Transfer => 'gray',
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
                SelectFilter::make('payment_method')
                    ->label(__('labels.payment_method'))
                    ->options([
                        PaymentMethod::DirectDebit->value => __('labels.payment_methods.direct_debit'),
                        PaymentMethod::Transfer->value => __('labels.payment_methods.transfer'),
                    ]),
                Filter::make('cleanup_direct_debit_without_mandate')
                    ->label(__('labels.cleanup_direct_debit_without_mandate'))
                    ->query(static fn (Builder $query): Builder => $query
                        ->where('payment_method', PaymentMethod::DirectDebit->value)
                        ->whereDoesntHave('paymentInformation')),
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
