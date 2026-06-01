<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;

final class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    private readonly InvoiceNumberGenerator $invoiceNumberGenerator;

    public function __construct()
    {
        $this->invoiceNumberGenerator = app(InvoiceNumberGenerator::class);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date'] = CarbonImmutable::now();
        $data['invoice_number'] = $this->invoiceNumberGenerator->generate()->value;

        return $data;
    }

    protected function afterFill(): void
    {
        $this->data['date'] = CarbonImmutable::now();
        $this->data['status'] = InvoiceStatus::Open;
        $this->data['invoice_number'] = '';
    }
}
