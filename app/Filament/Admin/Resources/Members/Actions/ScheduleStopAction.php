<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberStopService;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;

final class ScheduleStopAction
{
    public static function make(): Action
    {
        return Action::make('scheduleStop')
            ->label(__('labels.schedule_member_stop'))
            ->visible(static fn (Member $record): bool => $record->isActive()
                && !$record->isScheduled()
                && (auth()->user()?->can('scheduleStop', $record) ?? false))
            ->schema([
                DatePicker::make('stopped_at')
                    ->label(__('labels.stop_date'))
                    ->native(false)
                    ->format('d-m-Y')
                    ->default(now()->format('d-m-Y'))
                    ->minDate(now()->startOfDay())
                    ->required(),
            ])
            ->action(static function (Member $record, array $data, MemberStopService $service): void {
                $service->schedule(MemberId::create($record->id), CarbonImmutable::parse($data['stopped_at']));
                $record->refresh();
            })
            ->successNotificationTitle(__('notifications.member_stop_scheduled'));
    }
}
