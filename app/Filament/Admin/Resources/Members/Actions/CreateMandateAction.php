<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Billing\Payment\MandateDetails;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Members\MemberId;
use App\Domain\Members\PaymentMethod;
use App\Filament\Admin\Resources\Members\Schemas\MandateForm;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

final class CreateMandateAction
{
    public static function make(): Action
    {
        return Action::make('createMandate')
            ->label(__('labels.new_mandate'))
            ->icon(Heroicon::Plus)
            ->visible(static fn (): bool => auth()->user()?->can('update_member_payment_information') ?? false)
            ->modalDescription(__('labels.new_mandate_description'))
            ->schema(static function (Schema $schema, RelationManager $livewire): Schema {
                $member = $livewire->getOwnerRecord();
                $schema = $schema->operation(Operation::Create->value);

                return $member instanceof Member && $member->payment_method === PaymentMethod::Transfer
                    ? MandateForm::configureWithPaymentMethodSwitch($schema)
                    : MandateForm::configure($schema);
            })
            ->action(static function (
                RelationManager $livewire,
                array $data,
                MandateRepository $mandates,
            ): void {
                /** @var Member $member */
                $member = $livewire->getOwnerRecord();
                $memberId = MemberId::create($member->id);

                $mandateDetails = new MandateDetails(
                    iban: (string) $data['banking_account_number'],
                    bic: (string) $data['banking_bic'],
                    accountHolderName: (string) $data['banking_account_holder_name'],
                    signedAt: CarbonImmutable::parse((string) $data['mandate_accepted_date']),
                );

                $switchToDirectDebit = $data['switch_to_direct_debit'] ?? false;
                $mandates->create($memberId, $mandateDetails, $switchToDirectDebit ? PaymentMethod::DirectDebit : null);
            })
            ->successNotificationTitle(__('notifications.mandate_created'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
