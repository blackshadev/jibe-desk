<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\MandateId;
use App\Domain\Invoices\PaymentInformationId;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Invoices\SepaExportInvoice;
use App\Domain\Members\MemberId;
use App\Infrastructure\Invoices\SepaExportServiceImpl;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Tests\FeatureTestCase;

final class SepaExportServiceImplTest extends FeatureTestCase
{
    private SepaConfiguration $config;
    private InvoiceBatchRepositoryExpectation $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
        );

        $this->repo = InvoiceBatchRepositoryExpectation::create();
    }

    public function test_export_generates_valid_xml_for_direct_debit(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $xml = new DOMDocument();
        static::assertTrue($xml->loadXML($sepa->directDebit));

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');

        static::assertGreaterThan(0, $xpath->query('//pain:DrctDbtTxInf')->length);
        static::assertSame('', $sepa->creditTransfers);
    }

    public function test_export_generates_valid_xml_for_credit_transfer(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-002', amount: 25.00),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $xml = new DOMDocument();
        static::assertTrue($xml->loadXML($sepa->creditTransfers));

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        static::assertGreaterThan(0, $xpath->query('//pain:CdtTrfTxInf')->length);
        static::assertSame('', $sepa->directDebit);
    }

    public function test_export_generates_both_direct_debit_and_credit_transfer(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
            $this->createCreditInvoice(invoiceNumber: 'INV-002', amount: 25.00),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $debitXml = new DOMDocument();
        static::assertTrue($debitXml->loadXML($sepa->directDebit));
        $debitXpath = new DOMXPath($debitXml);
        $debitXpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');
        static::assertCount(1, $debitXpath->query('//pain:DrctDbtTxInf'));

        $creditXml = new DOMDocument();
        static::assertTrue($creditXml->loadXML($sepa->creditTransfers));
        $creditXpath = new DOMXPath($creditXml);
        $creditXpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');
        static::assertCount(1, $creditXpath->query('//pain:CdtTrfTxInf'));
    }

    public function test_export_is_empty_with_no_invoices(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, []);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertSame('', $sepa->creditTransfers);
    }

    public function test_export_skips_zero_amount_invoices(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 0.0),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertSame('', $sepa->creditTransfers);
    }

    public function test_direct_debit_contains_creditor_information(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertStringContainsString('Test Club', $sepa->directDebit);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->directDebit);
        static::assertStringContainsString('ABNANL2A', $sepa->directDebit);
        static::assertStringContainsString('NL12ZZZ1234567890', $sepa->directDebit);
    }

    public function test_credit_transfer_contains_creditor_information(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertStringContainsString('Test Club', $sepa->creditTransfers);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->creditTransfers);
        static::assertStringContainsString('ABNANL2A', $sepa->creditTransfers);
    }

    public function test_direct_debit_contains_invoice_information(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(
                invoiceNumber: 'INV-001',
                amount: 90.00,
                recipientName: 'Jan de Vries',
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
                mandateDate: '2025-06-01',
            ),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($sepa->directDebit));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');

        $transfer = $xpath->query('//pain:DrctDbtTxInf')->item(0);
        static::assertNotNull($transfer);

        static::assertStringContainsString('Jan de Vries', $transfer->C14N());
        static::assertStringContainsString('NL20RABO0123456789', $transfer->C14N());
        static::assertStringContainsString('RABONL2U', $transfer->C14N());

        $amountNode = $xpath->query('//pain:InstdAmt')->item(0);
        static::assertNotNull($amountNode);
        static::assertStringContainsString('90.00', $amountNode->nodeValue);
        static::assertStringContainsString('EUR', $amountNode->attributes->getNamedItem('Ccy')->nodeValue);

        $mandateNode = $xpath->query('//pain:MndtRltdInf')->item(0);
        static::assertNotNull($mandateNode);
        static::assertStringContainsString('C000001-000001', $mandateNode->C14N());
        static::assertStringContainsString('2025-06-01', $mandateNode->C14N());

        $collectionDateNode = $xpath->query('//pain:ReqdColltnDt')->item(0);
        static::assertSame('2026-06-30', $collectionDateNode->nodeValue);
    }

    public function test_credit_transfer_contains_invoice_information(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(
                invoiceNumber: 'INV-CR-001',
                amount: 50.00,
                recipientName: 'Jan de Vries',
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
            ),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($sepa->creditTransfers));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        $transfer = $xpath->query('//pain:CdtTrfTxInf')->item(0);
        static::assertNotNull($transfer);

        static::assertStringContainsString('Jan de Vries', $transfer->C14N());
        static::assertStringContainsString('NL20RABO0123456789', $transfer->C14N());
        static::assertStringContainsString('RABONL2U', $transfer->C14N());

        $amountNode = $xpath->query('//pain:InstdAmt')->item(0);
        static::assertNotNull($amountNode);
        static::assertStringContainsString('50.00', $amountNode->nodeValue);
        static::assertStringContainsString('EUR', $amountNode->attributes->getNamedItem('Ccy')->nodeValue);

        $endToEndNode = $xpath->query('//pain:EndToEndId')->item(0);
        static::assertNotNull($endToEndNode);
        static::assertSame('INV-CR-001', $endToEndNode->nodeValue);

        $collectionDateNode = $xpath->query('//pain:ReqdExctnDt/pain:Dt')->item(0);
        static::assertNotNull($collectionDateNode);
        static::assertSame('2026-06-30', $collectionDateNode->nodeValue);
    }

    public function test_direct_debit_multiple_invoices(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 10.00, recipientName: 'Alice', iban: 'NL91ABNA0417164300', bic: 'ABNANL2A'),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 20.00, recipientName: 'Bob', iban: 'NL20RABO0123456789', bic: 'RABONL2U'),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($sepa->directDebit));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');

        static::assertCount(2, $xpath->query('//pain:DrctDbtTxInf'));
        static::assertStringContainsString('Alice', $sepa->directDebit);
        static::assertStringContainsString('Bob', $sepa->directDebit);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->directDebit);
        static::assertStringContainsString('NL20RABO0123456789', $sepa->directDebit);
    }

    public function test_credit_transfer_multiple_invoices(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-001', amount: 15.00, recipientName: 'Alice', iban: 'NL91ABNA0417164300', bic: 'ABNANL2A'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-002', amount: 30.00, recipientName: 'Bob', iban: 'NL20RABO0123456789', bic: 'RABONL2U'),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($sepa->creditTransfers));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        static::assertCount(2, $xpath->query('//pain:CdtTrfTxInf'));
        static::assertStringContainsString('Alice', $sepa->creditTransfers);
        static::assertStringContainsString('Bob', $sepa->creditTransfers);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->creditTransfers);
        static::assertStringContainsString('NL20RABO0123456789', $sepa->creditTransfers);
    }

    public function test_mixed_debit_and_credit_invoices(): void
    {
        $batchId = InvoiceBatchId::create(1);

        $this->repo->expectsGetBatchDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 100.00, recipientName: 'Debtor Member'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-001', amount: 25.00, recipientName: 'Creditor Member'),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 50.00, recipientName: 'Another Debtor'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-002', amount: 10.00, recipientName: 'Another Creditor'),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $debitDom = new DOMDocument();
        static::assertTrue($debitDom->loadXML($sepa->directDebit));
        $debitXpath = new DOMXPath($debitDom);
        $debitXpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');
        static::assertCount(2, $debitXpath->query('//pain:DrctDbtTxInf'));
        static::assertStringContainsString('Debtor Member', $sepa->directDebit);
        static::assertStringContainsString('Another Debtor', $sepa->directDebit);

        $creditDom = new DOMDocument();
        static::assertTrue($creditDom->loadXML($sepa->creditTransfers));
        $creditXpath = new DOMXPath($creditDom);
        $creditXpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');
        static::assertCount(2, $creditXpath->query('//pain:CdtTrfTxInf'));
        static::assertStringContainsString('Creditor Member', $sepa->creditTransfers);
        static::assertStringContainsString('Another Creditor', $sepa->creditTransfers);
    }

    private function createDebitInvoice(
        string $invoiceNumber,
        float $amount,
        string $recipientName = 'Test Debtor',
        string $iban = 'NL20RABO0123456789',
        string $bic = 'RABONL2U',
        string $mandateDate = '2025-01-01',
    ): SepaExportInvoice {
        return new SepaExportInvoice(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: $invoiceNumber,
            recipientName: $recipientName,
            total: new CompoundPrice($amount, $amount * 0.21),
            iban: $iban,
            bic: $bic,
            mandateId: new MandateId(new MemberId(1), new PaymentInformationId(1)),
            mandateDate: CarbonImmutable::parse($mandateDate),
        );
    }

    private function createCreditInvoice(
        string $invoiceNumber,
        float $amount,
        string $recipientName = 'Test Creditor',
        string $iban = 'NL20RABO0123456789',
        string $bic = 'RABONL2U',
    ): SepaExportInvoice {
        return new SepaExportInvoice(
            invoiceId: InvoiceId::create(2),
            invoiceNumber: $invoiceNumber,
            recipientName: $recipientName,
            total: new CompoundPrice(-$amount, -$amount * 0.21),
            iban: $iban,
            bic: $bic,
            mandateId: new MandateId(new MemberId(2), new PaymentInformationId(2)),
            mandateDate: CarbonImmutable::now(),
        );
    }
}
