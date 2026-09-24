<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceNumberGenerator;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\PurchaseOrders\Helpers\MemberCreditorPrefill;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateInvoice extends CreateRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    private readonly InvoiceNumberGenerator $invoiceNumberGenerator;

    public function __construct()
    {
        $this->invoiceNumberGenerator = app(InvoiceNumberGenerator::class);
    }

    #[Override]
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

        $memberId = request()->query('member_id');
        if (!is_numeric($memberId)) {
            return;
        }

        $member = Member::find((int) $memberId);
        if ($member === null) {
            return;
        }

        $this->data['member_id'] = $member->id;
        $this->data['recipient_address'] = $member->address;
        $this->data['recipient_name'] = $member->name;
        $this->data['recipient_email'] = $member->email;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.invoice_created');
    }
}
