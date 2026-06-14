<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Infrastructure\Invoices\InvoiceBatchRepositoryDb;
use App\Infrastructure\Invoices\SepaConfiguration;
use App\Infrastructure\Invoices\SepaExportServiceImpl;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use DOMDocument;
use DOMXPath;
use Tests\FeatureTestCase;

final class SepaExportServiceImplTest extends FeatureTestCase
{
    private SepaConfiguration $config;
    private InvoiceBatchRepositoryDb $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            painFormat: 'pain.008.001.02',
        );

        $this->repo = new InvoiceBatchRepositoryDb();
    }

    public function testExportGeneratesValidXml(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL20RABO0123456789',
                'banking_bic' => 'RABONL2U',
                'uuid' => 'test-mandate-123',
                'mandate_accepted_date' => '2025-01-15',
            ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 25.00, 'quantity' => 1, 'vat' => 5.25]);

        $exportService = new SepaExportServiceImpl($this->repo, $this->config);

        $xml = $exportService->export(InvoiceBatchId::create($batch->id));

        static::assertNotEmpty($xml);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');

        static::assertGreaterThan(0, $xpath->query('//pain:DrctDbtTxInf')->length);
    }

    public function testExportWithEmptyBatchProducesValidXml(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $exportService = new SepaExportServiceImpl($this->repo, $this->config);

        $xml = $exportService->export(InvoiceBatchId::create($batch->id));

        static::assertNotEmpty($xml);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($xml));
    }

    public function testExportContainsCreditorInformation(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $exportService = new SepaExportServiceImpl($this->repo, $this->config);
        $xml = $exportService->export(InvoiceBatchId::create($batch->id));

        static::assertStringContainsString('Test Club', $xml);
        static::assertStringContainsString('NL91ABNA0417164300', $xml);
        static::assertStringContainsString('ABNANL2A', $xml);
        static::assertStringContainsString('NL12ZZZ1234567890', $xml);
    }

    public function testExportContainsInvoiceInformation(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL20RABO0123456789',
                'banking_bic' => 'RABONL2U',
                'uuid' => 'mandate-abc',
                'mandate_accepted_date' => '2025-06-01',
            ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['recipient_name' => 'Jan de Vries']);

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 30.00, 'quantity' => 3, 'vat' => 21.00]);

        $exportService = new SepaExportServiceImpl($this->repo, $this->config);
        $xml = $exportService->export(InvoiceBatchId::create($batch->id));

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');

        $transfer = $xpath->query('//pain:DrctDbtTxInf')->item(0);
        static::assertNotNull($transfer);

        $xpathTransfer = new DOMXPath($transfer->ownerDocument);
        $xpathTransfer->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');

        static::assertStringContainsString('Jan de Vries', $transfer->C14N());
        static::assertStringContainsString('NL20RABO0123456789', $transfer->C14N());
        static::assertStringContainsString('RABONL2U', $transfer->C14N());

        $amountNode = $xpath->query('//pain:InstdAmt')->item(0);
        static::assertNotNull($amountNode);

        static::assertStringContainsString('90.00', $amountNode->nodeValue);
        static::assertStringContainsString('EUR', $amountNode->attributes->getNamedItem('Ccy')->nodeValue);

        $mandateNode = $xpath->query('//pain:MndtRltdInf')->item(0);
        static::assertStringContainsString('mandate-abc', $mandateNode->C14N());
        static::assertStringContainsString('2025-06-01', $mandateNode->C14N());
    }

    public function testExportMultipleInvoices(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $member1 = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly([
            'member_id' => $member1->id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'uuid' => 'mandate-1',
            'mandate_accepted_date' => '2025-01-01',
        ]);
        $invoice1 = Invoice::factory()->forMember($member1)->forBatch($batch)->createQuietly(['recipient_name' => 'Alice']);
        InvoiceLine::factory()->state(['invoice_id' => $invoice1->id])->createQuietly(['price' => 10.00, 'quantity' => 1, 'vat' => 2.10]);

        $member2 = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly([
            'member_id' => $member2->id,
            'banking_account_number' => 'NL20RABO0123456789',
            'banking_bic' => 'RABONL2U',
            'uuid' => 'mandate-2',
            'mandate_accepted_date' => '2025-02-01',
        ]);
        $invoice2 = Invoice::factory()->forMember($member2)->forBatch($batch)->createQuietly(['recipient_name' => 'Bob']);
        InvoiceLine::factory()->state(['invoice_id' => $invoice2->id])->createQuietly(['price' => 20.00, 'quantity' => 1, 'vat' => 4.20]);

        $exportService = new SepaExportServiceImpl($this->repo, $this->config);
        $xml = $exportService->export(InvoiceBatchId::create($batch->id));

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');

        static::assertCount(2, $xpath->query('//pain:DrctDbtTxInf'));
        static::assertStringContainsString('Alice', $xml);
        static::assertStringContainsString('Bob', $xml);
        static::assertStringContainsString('NL91ABNA0417164300', $xml);
        static::assertStringContainsString('NL20RABO0123456789', $xml);
    }
}
