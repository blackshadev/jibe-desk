<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Billing\Payment\MandateCorrection;
use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Filament\Admin\Resources\Members\Schemas\MandateForm;
use App\Models\PaymentInformation;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Support\Icons\Heroicon;

final class EditMandateAction
{
    public static function make(): Action
    {
        return Action::make('editMandate')
            ->label(__('labels.edit'))
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(__('labels.edit_mandate'))
            ->modalDescription(__('labels.edit_mandate_help'))
            ->visible(
                static fn (PaymentInformation $record): bool => $record->revoked_at === null && (auth()->user()?->can('update_member_payment_information') ?? false),
            )
            ->fillForm(static fn (PaymentInformation $record): array => [
                'banking_account_number' => $record->banking_account_number,
                'banking_bic' => $record->banking_bic,
                'banking_account_holder_name' => $record->banking_account_holder_name,
                'mandate_accepted_date' => $record->mandate_accepted_date,
            ])
            ->schema(static fn (Schema $schema): Schema => MandateForm::configure($schema->operation(Operation::Edit->value)))
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
            ->successNotificationTitle(__('notifications.mandate_updated'));
    }
}
