<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Billing\Payment\MandateRepository;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class RevokeMandateAction
{
    public static function make(): Action
    {
        return Action::make('revokeMandate')
            ->label(__('labels.revoke_mandate'))
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('labels.revoke_mandate'))
            ->modalDescription(__('labels.revoke_mandate_warning'))
            ->visible(
                static fn (PaymentInformation $record): bool => $record->revoked_at === null && (auth()->user()?->can('update_member_payment_information') ?? false),
            )
            ->action(static function (PaymentInformation $record, MandateRepository $mandates): void {
                $mandates->revoke(
                    PaymentInformationId::create($record->id),
                    CarbonImmutable::now(),
                );
            })
            ->successNotificationTitle(__('notifications.mandate_revoked'));
    }
}
