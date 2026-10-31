<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberStopService;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Livewire\Component;

final class ScheduleStopAction
{
    public static function make(): Action
    {
        return Action::make('scheduleStop')
            ->label(__('labels.schedule_member_stop'))
            ->visible(static fn (Member $record): bool => $record->isActive() && !$record->isScheduledForStopping() && (auth()->user()?->can('scheduleStop', $record) ?? false))
            ->schema([
                DatePicker::make('stop_requested_at')
                    ->label(__('labels.stop_requested_at'))
                    ->native(false)
                    ->format('d-m-Y')
                    ->default(CarbonImmutable::now()->format('d-m-Y'))
                    ->required(),
                DatePicker::make('stopped_at')
                    ->label(__('labels.stop_date'))
                    ->native(false)
                    ->format('d-m-Y')
                    ->default(CarbonImmutable::now()->endOfYear()->format('d-m-Y'))
                    ->minDate(CarbonImmutable::now()->startOfDay())
                    ->required(),
            ])
            ->action(static function (Member $record, array $data, MemberStopService $service): void {
                $service->schedule(
                    MemberId::create($record->id),
                    CarbonImmutable::parse($data['stopped_at']),
                    CarbonImmutable::parse($data['stop_requested_at']),
                );
                $record->refresh();
            })
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'))
            ->successNotificationTitle(__('notifications.member_stop_scheduled'));
    }
}
