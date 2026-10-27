<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Filament\Admin\RelationManagers\MemberRelationManager;
use App\Filament\Admin\Resources\Members\Actions\CreateMandateAction;
use App\Filament\Admin\Resources\Members\Actions\EditMandateAction;
use App\Filament\Admin\Resources\Members\Actions\RevokeMandateAction;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Override;

final class MandatesRelationManager extends MemberRelationManager
{
    #[Override]
    protected static string $relationship = 'paymentInformations';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (!$user instanceof User || $user->isMember()) {
            return false;
        }

        return $user->can('view_member_payment_information');
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return !(auth()->user()?->can('update_member_payment_information') ?? false);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mandate_reference')
                    ->label(__('labels.mandate_reference')),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number')),
                TextColumn::make('mandate_accepted_date')
                    ->label(__('labels.mandate_date'))
                    ->date(),
                TextColumn::make('last_collected_at')
                    ->label(__('labels.last_collected_at'))
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('expiration_status')
                    ->label(__('labels.expiration_status'))
                    ->badge()
                    ->formatStateUsing(static fn (MandateExpirationStatus $state): string => __('labels.expiration_statuses.' . $state->value))
                    ->color(static fn (MandateExpirationStatus $state): string => match ($state) {
                        MandateExpirationStatus::Active => 'success',
                        MandateExpirationStatus::Expired => 'warning',
                        MandateExpirationStatus::Revoked => 'gray',
                    }),
                TextColumn::make('revoked_at')
                    ->label(__('labels.revoked_at'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('mandate_accepted_date', 'desc')
            ->headerActions([
                CreateMandateAction::make(),
            ])
            ->recordActions([
                EditMandateAction::make(),
                RevokeMandateAction::make(),
            ]);
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.mandates');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.mandate'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.mandates'));
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
