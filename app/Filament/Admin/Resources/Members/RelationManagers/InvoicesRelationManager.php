<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Domain\Invoices\GenerateInvoice;
use App\Domain\Invoices\InvoiceGenerator;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Members\MemberId;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;

final class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number')),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state) => __('labels.invoice_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
            ])
            ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
            ->headerActions([
                CreateAction::make(),
                Action::make('generate')
                    ->label(__('labels.generate_invoice'))
                    ->action(static function (RelationManager $livewire, InvoiceGenerator $generator, Action $action): bool {
                        /** @var Member $member */
                        $member = $livewire->getOwnerRecord();
                        $command = new GenerateInvoice(
                            MemberId::create($member->id),
                            CarbonImmutable::now(),
                        );

                        $id = $generator->generate($command);

                        if ($id === null) {
                            Notification::make()
                                ->title(__('notifications.nothing_to_invoice'))
                                ->info()
                                ->send();

                            $action->cancel();
                        }

                        $livewire->redirect(InvoiceResource::getUrl('edit', ['record' => $id?->value]));

                        return true;
                    })
                    ->successNotificationTitle(__('notifications.invoice_generated')),
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.invoice'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.invoices'));
    }
}
