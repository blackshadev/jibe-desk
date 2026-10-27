<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\MandateId;
use App\Domain\Billing\Payment\PaymentInformationId;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Billing\Payment\SepaExport;
use App\Domain\Billing\Payment\SepaExportInvoice;
use App\Domain\Billing\Payment\SepaExportPurchaseOrder;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Members\MemberId;
use App\Domain\SharedKernel\CompoundPrice;
use App\Infrastructure\Billing\Payment\SepaExportServiceImpl;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Override;
use Tests\FeatureTestCase;

final class SepaExportServiceImplTest extends FeatureTestCase
{
    private SepaConfiguration $config;
    private SepaBatchRepositoryExpectation $repo;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            noticePeriodDays: 14,
        );

        $this->repo = SepaBatchRepositoryExpectation::create();
    }

    public function test_export_generates_valid_xml_for_direct_debit(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-002', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
            $this->createCreditInvoice(invoiceNumber: 'INV-002', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, []);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertSame('', $sepa->creditTransfers);
    }

    public function test_export_skips_zero_amount_invoices(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 0.0),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertSame('', $sepa->creditTransfers);
    }

    public function test_direct_debit_contains_creditor_information(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertStringContainsString('Test Club', $sepa->directDebit);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->directDebit);
        static::assertStringContainsString('ABNANL2A', $sepa->directDebit);
        static::assertStringContainsString('NL12ZZZ1234567890', $sepa->directDebit);
    }

    public function test_credit_transfer_contains_creditor_information(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-001', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertStringContainsString('Test Club', $sepa->creditTransfers);
        static::assertStringContainsString('NL91ABNA0417164300', $sepa->creditTransfers);
        static::assertStringContainsString('ABNANL2A', $sepa->creditTransfers);
    }

    public function test_direct_debit_contains_invoice_information(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
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
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(
                invoiceNumber: 'INV-CR-001',
                amount: 50.00,
                recipientName: 'Jan de Vries',
                iban: 'NL20RABO0123456789',
                bic: 'RABONL2U',
            ),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 10.00, recipientName: 'Alice', iban: 'NL91ABNA0417164300', bic: 'ABNANL2A'),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 20.00, recipientName: 'Bob', iban: 'NL20RABO0123456789', bic: 'RABONL2U'),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-001', amount: 15.00, recipientName: 'Alice', iban: 'NL91ABNA0417164300', bic: 'ABNANL2A'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-002', amount: 30.00, recipientName: 'Bob', iban: 'NL20RABO0123456789', bic: 'RABONL2U'),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 100.00, recipientName: 'Debtor Member'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-001', amount: 25.00, recipientName: 'Creditor Member'),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 50.00, recipientName: 'Another Debtor'),
            $this->createCreditInvoice(invoiceNumber: 'INV-CR-002', amount: 10.00, recipientName: 'Another Creditor'),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);

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

    public function test_purchase_orders_are_emitted_into_credit_transfers(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, []);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, [
            new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create(42),
                creditorName: 'Supplier Ltd',
                creditorIban: 'NL20RABO0123456789',
                total: new CompoundPrice(90.00, 18.90),
            ),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        $dom = new DOMDocument();
        static::assertTrue($dom->loadXML($sepa->creditTransfers));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        static::assertCount(1, $xpath->query('//pain:CdtTrfTxInf'));

        $transfer = $xpath->query('//pain:CdtTrfTxInf')->item(0);
        static::assertNotNull($transfer);
        static::assertStringContainsString('Supplier Ltd', $transfer->C14N());
        static::assertStringContainsString('NL20RABO0123456789', $transfer->C14N());

        $amountNode = $xpath->query('//pain:InstdAmt')->item(0);
        static::assertNotNull($amountNode);
        static::assertStringContainsString('90.00', $amountNode->nodeValue);

        $endToEndNode = $xpath->query('//pain:EndToEndId')->item(0);
        static::assertNotNull($endToEndNode);
        static::assertSame('PO-42', $endToEndNode->nodeValue);

        static::assertSame('', $sepa->directDebit);
    }

    public function test_purchase_orders_do_not_add_creditor_agent_element(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, []);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, [
            new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create(7),
                creditorName: 'Supplier Ltd',
                creditorIban: 'NL20RABO0123456789',
                total: new CompoundPrice(10.00, 2.10),
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
        static::assertCount(0, $xpath->query('//pain:CdtTrfTxInf//pain:CdtrAgt//pain:BICFI'));
        static::assertStringContainsString('NOTPROVIDED', $transfer->C14N());
    }

    public function test_zero_amount_purchase_orders_are_skipped(): void
    {
        $batchId = SepaBatchId::create(1);

        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, []);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, [
            new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create(9),
                creditorName: 'Supplier Ltd',
                creditorIban: 'NL20RABO0123456789',
                total: new CompoundPrice(0.0, 0.0),
            ),
        ]);

        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);
        $sepa = $exportService->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertSame('', $sepa->creditTransfers);
    }


    public function test_export_creates_one_direct_debit_block_per_sequence_type(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-FRST', amount: 25.00, sequenceType: SequenceType::First),
            $this->createDebitInvoice(invoiceNumber: 'INV-RCUR', amount: 30.00, sequenceType: SequenceType::Recurring),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        static::assertSame(2, $xpath->query('//pain:PmtInf')->length);
        static::assertEqualsCanonicalizing(
            ['FRST', 'RCUR'],
            $this->sequenceTypes($xpath),
        );
    }

    public function test_export_emits_only_the_frst_block_for_an_all_first_batch(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00, sequenceType: SequenceType::First),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 15.00, sequenceType: SequenceType::First),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        static::assertSame(1, $xpath->query('//pain:PmtInf')->length);
        static::assertSame(['FRST'], $this->sequenceTypes($xpath));
    }

    public function test_export_emits_only_the_rcur_block_for_an_all_recurring_batch(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-001', amount: 25.00),
            $this->createDebitInvoice(invoiceNumber: 'INV-002', amount: 15.00),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        static::assertSame(1, $xpath->query('//pain:PmtInf')->length);
        static::assertSame(['RCUR'], $this->sequenceTypes($xpath));
    }

    public function test_export_uses_the_batch_transfer_date_on_both_direct_debit_blocks(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-FRST', amount: 25.00, sequenceType: SequenceType::First),
            $this->createDebitInvoice(invoiceNumber: 'INV-RCUR', amount: 30.00),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        $dates = [];

        foreach ($xpath->query('//pain:PmtInf/pain:ReqdColltnDt') as $node) {
            $dates[] = $node->textContent;
        }

        static::assertSame(['2026-06-30', '2026-06-30'], $dates);
    }

    public function test_export_gives_each_direct_debit_block_a_unique_payment_information_id(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-FRST', amount: 25.00, sequenceType: SequenceType::First),
            $this->createDebitInvoice(invoiceNumber: 'INV-RCUR', amount: 30.00),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        $ids = [];

        foreach ($xpath->query('//pain:PmtInf/pain:PmtInfId') as $node) {
            $ids[] = $node->textContent;
        }

        static::assertSame(['BATCH-1-FRST', 'BATCH-1-RCUR'], $ids);
        static::assertCount(2, array_unique($ids));
    }

    public function test_export_groups_each_invoice_into_exactly_one_block(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->expectInvoices($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-FRST', amount: 25.00, sequenceType: SequenceType::First),
            $this->createDebitInvoice(invoiceNumber: 'INV-RCUR-1', amount: 30.00),
            $this->createDebitInvoice(invoiceNumber: 'INV-RCUR-2', amount: 35.00),
        ]);

        $xpath = $this->directDebitXpath($this->export($batchId));

        $blockIds = [];

        foreach ($xpath->query('//pain:PmtInf') as $block) {
            $sequenceType = $xpath->query('./pain:PmtTpInf/pain:SeqTp', $block)->item(0)?->textContent;

            foreach ($xpath->query('.//pain:EndToEndId', $block) as $endToEndId) {
                $blockIds[$sequenceType][] = $endToEndId->textContent;
            }
        }

        static::assertSame(['INV-FRST'], $blockIds['FRST']);
        static::assertEqualsCanonicalizing(['INV-RCUR-1', 'INV-RCUR-2'], $blockIds['RCUR']);

        $allIds = array_merge(...array_values($blockIds));
        static::assertCount(count($allIds), array_unique($allIds));
    }

    public function test_export_emits_no_direct_debit_block_without_debit_invoices(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createCreditInvoice(invoiceNumber: 'INV-CREDIT', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, [
            new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create(9),
                creditorName: 'Supplier Ltd',
                creditorIban: 'NL20RABO0123456789',
                total: new CompoundPrice(10.0, 2.1),
            ),
        ]);

        $sepa = $this->export($batchId);

        static::assertSame('', $sepa->directDebit);
        static::assertNotSame('', $sepa->creditTransfers);
    }

    public function test_export_leaves_refunds_and_purchase_orders_in_the_credit_transfer_file(): void
    {
        $batchId = SepaBatchId::create(1);
        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, [
            $this->createDebitInvoice(invoiceNumber: 'INV-FRST', amount: 25.00, sequenceType: SequenceType::First),
            $this->createCreditInvoice(invoiceNumber: 'INV-CREDIT', amount: 25.00),
        ]);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, [
            new SepaExportPurchaseOrder(
                purchaseOrderId: PurchaseOrderId::create(9),
                creditorName: 'Supplier Ltd',
                creditorIban: 'NL20RABO0123456789',
                total: new CompoundPrice(10.0, 2.1),
            ),
        ]);

        $sepa = $this->export($batchId);

        $debitXpath = $this->directDebitXpath($sepa);
        static::assertSame(1, $debitXpath->query('//pain:PmtInf')->length);
        static::assertSame(1, $debitXpath->query('//pain:DrctDbtTxInf')->length);

        $creditXml = new DOMDocument();
        static::assertTrue($creditXml->loadXML($sepa->creditTransfers));
        $creditXpath = new DOMXPath($creditXml);
        $creditXpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');

        static::assertSame(2, $creditXpath->query('//pain:CdtTrfTxInf')->length);
    }

    private function export(SepaBatchId $batchId): SepaExport
    {
        $exportService = new SepaExportServiceImpl($this->repo->mock, $this->config);

        return $exportService->export($batchId);
    }

    /** @param list<SepaExportInvoice> $invoices */
    private function expectInvoices(SepaBatchId $batchId, array $invoices): void
    {
        $this->repo->expectsGetSepaTransferDate($batchId, CarbonImmutable::parse('2026-06-30'));
        $this->repo->expectsGetInvoicesForExport($batchId, $invoices);
        $this->repo->expectsGetPurchaseOrdersForExport($batchId, []);
    }

    private function directDebitXpath(SepaExport $sepa): DOMXPath
    {
        $xml = new DOMDocument();
        static::assertTrue($xml->loadXML($sepa->directDebit));

        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.09');

        return $xpath;
    }

    /** @return list<string> */
    private function sequenceTypes(DOMXPath $xpath): array
    {
        $sequenceTypes = [];

        foreach ($xpath->query('//pain:PmtInf/pain:PmtTpInf/pain:SeqTp') as $node) {
            $sequenceTypes[] = $node->textContent;
        }

        return $sequenceTypes;
    }

    private function createDebitInvoice(
        string $invoiceNumber,
        float $amount,
        string $recipientName = 'Test Debtor',
        string $iban = 'NL20RABO0123456789',
        string $bic = 'RABONL2U',
        string $mandateDate = '2025-01-01',
        SequenceType $sequenceType = SequenceType::Recurring,
    ): SepaExportInvoice {
        return new SepaExportInvoice(
            invoiceId: InvoiceId::create(1),
            invoiceNumber: $invoiceNumber,
            recipientName: $recipientName,
            total: new CompoundPrice($amount, $amount * 0.21),
            iban: $iban,
            bic: $bic,
            mandateId: new MandateId(new MemberId(1), new PaymentInformationId(1)),
            paymentInformationId: new PaymentInformationId(1),
            mandateDate: CarbonImmutable::parse($mandateDate),
            lastCollectedAt: null,
            sequenceType: $sequenceType,
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
            paymentInformationId: new PaymentInformationId(2),
            mandateDate: CarbonImmutable::now(),
            lastCollectedAt: null,
            sequenceType: SequenceType::Recurring,
        );
    }
}
