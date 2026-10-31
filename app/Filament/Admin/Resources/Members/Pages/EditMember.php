<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\Actions\AcceptMemberAction;
use App\Filament\Admin\Resources\Members\Actions\CancelStopAction;
use App\Filament\Admin\Resources\Members\Actions\CreateUserAccountAction;
use App\Filament\Admin\Resources\Members\Actions\ScheduleStopAction;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use Override;

final class EditMember extends EditRecord
{
    #[Override]
    protected static string $resource = MemberResource::class;

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.member');
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateUserAccountAction::make(),
                ScheduleStopAction::make(),
                CancelStopAction::make(),
                AcceptMemberAction::make(),
                DeleteAction::make()->visible(static fn (Member $record): bool => $record->isPending()),
            ])
                ->button()
                ->color('gray'),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['infix_name'] === null) {
            $data['infix_name'] = '';
        }

        return $data;
    }

    #[Override]
    protected function getSavedNotification(): Notification
    {
        $record = $this->record;

        $body = [];

        if ($record->wasChanged('membership_id')) {
            $body[] = __('notifications.membership_changed_billing_applied');
        }

        if ($record->wasChanged('is_volunteer')) {
            $body[] = __('notifications.member_volunteer_updated');
        }

        return Notification::make()->success()->title(__('notifications.member_updated'))->body(implode('', $body));
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
