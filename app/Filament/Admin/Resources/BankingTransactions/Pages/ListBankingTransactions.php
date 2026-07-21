<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Domain\BankTransactions\BankTransactionImportService;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListBankingTransactions extends ListRecords
{
    protected static string $resource = BankingTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('importMt940')
                ->label(__('labels.import_mt940'))
                ->modalHeading(__('labels.import_mt940'))
                ->schema([
                    FileUpload::make('mt940_file')
                        ->label(__('labels.mt940_file'))
                        ->directory('mt940-imports')
                        ->disk('local')
                        ->required(),
                ])
                ->action(static function (Page $livewire, array $data, BankTransactionImportService $importService): void {
                    $result = $importService->importFromFile(
                        storage_path('app/private/' . $data['mt940_file']),
                    );

                    Notification::make()
                        ->title(__('labels.import_complete'))
                        ->body(__('labels.import_result', [
                            'imported' => $result['imported'],
                            'skipped' => $result['skipped'],
                        ]))
                        ->success()
                        ->send();

                    $livewire->dispatch('refreshTable');
                }),
            CreateAction::make(),
        ];
    }
}
