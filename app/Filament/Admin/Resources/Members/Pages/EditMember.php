<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Pages;

use App\Filament\Admin\Resources\Members\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return __('labels.member');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(function (): void {
                    Notification::make()->success()->title(__('notifications.member_deleted'))->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['infix_name'])) {
            $data['infix_name'] = '';
        }

        return $data;
    }

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
}
