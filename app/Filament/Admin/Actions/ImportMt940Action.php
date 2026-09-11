<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\BankTransactions\BankTransactionImportService;
use App\Domain\BankTransactions\UnknownBankAccountException;
use App\Domain\Jobs\MatchBankingTransactionsJob;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class ImportMt940Action
{
    public static function make(): Action
    {
        return Action::make('importMt940')
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
                try {
                    $result = $importService->importFromFile(
                        storage_path('app/private/' . $data['mt940_file']),
                    );
                } catch (UnknownBankAccountException $e) {
                    Notification::make()
                        ->title(__('labels.import_failed'))
                        ->body(__('labels.import_failed_unknown_bank_account', [
                            'iban' => $e->iban,
                        ]))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('labels.import_complete'))
                    ->body(__('labels.import_result', [
                        'imported' => $result['imported'],
                        'skipped' => $result['skipped'],
                    ]))
                    ->success()
                    ->send();

                if ($result['imported'] > 0) {
                    MatchBankingTransactionsJob::dispatch();
                }

                $livewire->dispatch('refreshTable');
            });
    }
}
