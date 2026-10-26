<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Billing\Payment\MandateCorrection;
use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateExpirationStatus;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Members\MemberId;
use App\Filament\Admin\RelationManagers\MemberRelationManager;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Intervention\Validation\Rules\Bic;
use Intervention\Validation\Rules\Iban;
use Livewire\Attributes\On;
use Livewire\Component;
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
                TextColumn::make('mandate_state')
                    ->label(__('labels.mandate_state'))
                    ->badge()
                    ->state(static fn (PaymentInformation $record): MandateExpirationStatus => $record->expiration_status)
                    ->formatStateUsing(static fn (MandateExpirationStatus $state): string => __('labels.mandate_states.' . $state->value))
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
                Action::make('createMandate')
                    ->label(__('labels.new_mandate'))
                    ->icon(Heroicon::Plus)
                    ->visible(static fn (): bool => auth()->user()?->can('update_member_payment_information') ?? false)
                    ->modalDescription(__('labels.new_mandate_description'))
                    ->schema(self::mandateFields())
                    ->action(static function (
                        RelationManager $livewire,
                        array $data,
                        MandateRepository $mandates,
                    ): void {
                        /** @var Member $member */
                        $member = $livewire->getOwnerRecord();

                        $mandates->create(
                            MemberId::create($member->id),
                            new MandateDetails(
                                iban: (string) $data['banking_account_number'],
                                bic: (string) $data['banking_bic'],
                                accountHolderName: (string) $data['banking_account_holder_name'],
                                signedAt: CarbonImmutable::parse((string) $data['mandate_accepted_date']),
                            ),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_created'))
                    ->after(static fn (Component $livewire) => $livewire->dispatch('refresh')),
            ])
            ->recordActions([
                Action::make('editMandate')
                    ->label(__('labels.edit'))
                    ->icon(Heroicon::PencilSquare)
                    ->modalHeading(__('labels.edit_mandate'))
                    ->modalDescription(__('labels.edit_mandate_help'))
                    ->visible(
                        static fn (PaymentInformation $record): bool => $record->revoked_at === null
                            && (auth()->user()?->can('update_member_payment_information') ?? false),
                    )
                    ->fillForm(static fn (PaymentInformation $record): array => [
                        'banking_account_number' => $record->banking_account_number,
                        'banking_bic' => $record->banking_bic,
                        'banking_account_holder_name' => $record->banking_account_holder_name,
                        'mandate_accepted_date' => $record->mandate_accepted_date,
                    ])
                    ->schema(self::editMandateFields())
                    ->action(static function (
                        PaymentInformation $record,
                        array $data,
                        MandateRepository $mandates,
                    ): void {
                        $mandates->update(
                            PaymentInformationId::create($record->id),
                            new MandateCorrection(
                                bic: (string) $data['banking_bic'],
                                accountHolderName: (string) $data['banking_account_holder_name'],
                            ),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_updated')),
                Action::make('revokeMandate')
                    ->label(__('labels.revoke_mandate'))
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('labels.revoke_mandate'))
                    ->modalDescription(__('labels.revoke_mandate_warning'))
                    ->visible(
                        static fn (PaymentInformation $record): bool => $record->revoked_at === null
                            && (auth()->user()?->can('update_member_payment_information') ?? false),
                    )
                    ->action(static function (PaymentInformation $record, MandateRepository $mandates): void {
                        $mandates->revoke(
                            PaymentInformationId::create($record->id),
                            CarbonImmutable::now(),
                        );
                    })
                    ->successNotificationTitle(__('notifications.mandate_revoked')),
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
    public function refresh(): void {}

    /** @return array<int, TextInput|DatePicker> */
    private static function mandateFields(): array
    {
        return [
            TextInput::make('banking_account_number')
                ->label(__('labels.banking_account_number'))
                ->required()
                ->maxLength(34)
                ->rule(new Iban()),
            TextInput::make('banking_bic')
                ->label(__('labels.banking_bic'))
                ->required()
                ->maxLength(11)
                ->rule(new Bic()),
            TextInput::make('banking_account_holder_name')
                ->label(__('labels.banking_account_holder_name'))
                ->required()
                ->maxLength(255),
            DatePicker::make('mandate_accepted_date')
                ->label(__('labels.mandate_date'))
                ->required()
                ->native(false)
                ->maxDate(now()),
        ];
    }

    /**
     * IBAN and signed date identify the signed authorisation, so the edit form shows them read-only.
     * A change to either is a re-authorisation and goes through the "Nieuwe machtiging" action.
     *
     * @return array<int, TextInput|DatePicker>
     */
    private static function editMandateFields(): array
    {
        return [
            TextInput::make('banking_account_number')
                ->label(__('labels.banking_account_number'))
                ->disabled()
                ->dehydrated(false),
            TextInput::make('banking_bic')
                ->label(__('labels.banking_bic'))
                ->required()
                ->maxLength(11)
                ->rule(new Bic()),
            TextInput::make('banking_account_holder_name')
                ->label(__('labels.banking_account_holder_name'))
                ->required()
                ->maxLength(255),
            DatePicker::make('mandate_accepted_date')
                ->label(__('labels.mandate_date'))
                ->format('d-m-Y')
                ->native(false)
                ->disabled()
                ->dehydrated(false),
        ];
    }
}
