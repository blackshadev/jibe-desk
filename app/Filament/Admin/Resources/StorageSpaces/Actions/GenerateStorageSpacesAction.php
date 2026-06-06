<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Actions;

use App\Models\StorageSpace;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

final class GenerateStorageSpacesAction
{
    public static function make(): Action
    {
        return Action::make('generate_storage_spaces')
            ->label(__('labels.generate_storage_spaces'))
            ->icon(Heroicon::SquaresPlus)
            ->schema([
                TextInput::make('location')
                    ->label(__('labels.location'))
                    ->required(),
                TextInput::make('from_number')
                    ->label(__('labels.from_number'))
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('to_number')
                    ->label(__('labels.to_number'))
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->gte('from_number'),
            ])
            ->action(static function (array $data): void {
                $location = $data['location'];
                $fromNumber = (int) $data['from_number'];
                $toNumber = (int) $data['to_number'];

                for ($number = $fromNumber; $number <= $toNumber; $number++) {
                    StorageSpace::firstOrCreate([
                        'location' => $location,
                        'number' => $number,
                    ]);
                }
            })
            ->successNotificationTitle(__('notifications.storage_spaces_generated'));
    }
}
