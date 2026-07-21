<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;

final class CreateInvoiceFromTransactionAction
{
    public static function make(): Action
    {
        return Action::make('createInvoiceFromTransaction')
            ->label(__('labels.create_invoice_from_transaction'))
            ->modalHeading(__('labels.create_invoice_from_transaction'))
            ->visible(IsOpen::checkOwner(...))
            ->schema(static function (RelationManager $livewire): array {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                return [
                    Select::make('member_id')
                        ->label(__('labels.member'))
                        ->options(static fn () => Member::query()
                            ->orderBy('last_name')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(static fn (Member $member): array => [
                                $member->id => $member->name,
                            ]))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(static function (?int $state, Set $set): void {
                            if ($state === null) {
                                return;
                            }

                            $member = Member::findOrFail($state);

                            $set('recipient_name', $member->name);
                            $set('recipient_address', $member->address);
                            $set('recipient_email', $member->email);
                        }),
                    TextInput::make('recipient_name')
                        ->label(__('labels.recipient_name'))
                        ->hidden()
                        ->dehydrated()
                        ->required(),
                    TextInput::make('recipient_address')
                        ->label(__('labels.recipient_address'))
                        ->hidden()
                        ->dehydrated()
                        ->required(),
                    TextInput::make('recipient_email')
                        ->label(__('labels.recipient_email'))
                        ->hidden()
                        ->dehydrated()
                        ->required(),
                    DatePicker::make('date')
                        ->label(__('labels.invoice_date'))
                        ->native(false)
                        ->format('d-m-Y')
                        ->hidden()
                        ->dehydrated()
                        ->required(),
                    TextInput::make('line_description')
                        ->label(__('labels.description'))
                        ->default($record->description)
                        ->columnSpanFull()
                        ->required(),
                    TextInput::make('line_price')
                        ->label(__('labels.price'))
                        ->prefix('€')
                        ->default($record->unmatched_amount)
                        ->required(),
                    TextInput::make('line_quantity')
                        ->label(__('labels.quantity'))
                        ->default(1)
                        ->required(),
                    Select::make('cost_center_id')
                        ->label(__('labels.cost_center'))
                        ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ];
            })
            ->action(static function (
                array $data,
                RelationManager $livewire,
                InvoiceNumberGenerator $invoiceNumberGenerator,
                BankTransactionService $bankingTransaction,
            ): void {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                $member = Member::findOrFail($data['member_id']);

                $invoice = Invoice::create([
                    'member_id' => $data['member_id'],
                    'date' => $record->date,
                    'status' => InvoiceStatus::Open,
                    'invoice_number' => $invoiceNumberGenerator->generate()->value,
                    'recipient_name' => $data['recipient_name'] ?? $member->name,
                    'recipient_address' => $data['recipient_address'] ?? $member->address,
                    'recipient_email' => $data['recipient_email'] ?? $member->email,
                ]);

                $invoice
                    ->lines()
                    ->create([
                        'description' => $data['line_description'],
                        'price' => $data['line_price'],
                        'vat' => (float) $data['line_price'] * 0.21,
                        'quantity' => $data['line_quantity'],
                        'cost_center_id' => $data['cost_center_id'],
                    ]);

                $bankingTransaction->attachInvoice(
                    BankTransactionId::create($record->id),
                    InvoiceId::create($invoice->id),
                );

                $livewire->dispatch('refresh');
            })
            ->successNotificationTitle(__('notifications.invoice_created_and_attached'));
    }
}
