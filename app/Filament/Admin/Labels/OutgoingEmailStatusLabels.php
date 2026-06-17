<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Mail\OutgoingEmailStatus;

final class OutgoingEmailStatusLabels
{
    public static function options(): array
    {
        return [
            OutgoingEmailStatus::Queued->value => __('labels.outgoing_email_status.queued'),
            OutgoingEmailStatus::Failed->value => __('labels.outgoing_email_status.failed'),
            OutgoingEmailStatus::Sent->value => __('labels.outgoing_email_status.sent'),
        ];
    }

    public static function label(OutgoingEmailStatus $state): string
    {
        return match ($state) {
            OutgoingEmailStatus::Queued => __('labels.outgoing_email_status.queued'),
            OutgoingEmailStatus::Failed => __('labels.outgoing_email_status.failed'),
            OutgoingEmailStatus::Sent => __('labels.outgoing_email_status.sent'),
        };
    }

    public static function color(OutgoingEmailStatus $state): string
    {
        return match ($state) {
            OutgoingEmailStatus::Queued => 'info',
            OutgoingEmailStatus::Failed => 'danger',
            OutgoingEmailStatus::Sent => 'success',
        };
    }
}
