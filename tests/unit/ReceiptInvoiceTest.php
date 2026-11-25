<?php

use App\Libraries\ReceiptInvoice;
use CodeIgniter\Test\CIUnitTestCase;

final class ReceiptInvoiceTest extends CIUnitTestCase
{
    private function config(string $status = ''): \Config\ReceiptInvoice
    {
        $config = new \Config\ReceiptInvoice();
        $config->taxStatus = $status;
        return $config;
    }

    public function testUnconfiguredAndLegacySalesKeepTheirReferencesWithoutInventedTax(): void
    {
        $sale = ['invoice_no' => 'INV-original', 'receipt_metadata' => ReceiptInvoice::snapshot(null, $this->config())];
        $result = ReceiptInvoice::data($sale, []);
        $this->assertSame('INV-original', $result['serial']);
        $this->assertFalse($result['series_recorded']);
        $this->assertSame('Not configured', $result['tin']);
        $this->assertSame('Not configured', $result['tax_status']);
        foreach ([null, '', 'broken json', '{"version":1}'] as $raw) {
            $result = ReceiptInvoice::data(['invoice_no' => 'OLD-1', 'receipt_metadata' => $raw], []);
            $this->assertFalse($result['recorded']);
            $this->assertSame('OLD-1', $result['serial']);
            $this->assertSame('Not recorded', $result['tin']);
            $this->assertFalse($result['tax_recorded']);
        }
    }

    public function testMixedVatBreakdownReconcilesAndMissingClassificationsRemainUnknown(): void
    {
        $sale = ['final_total' => 250.8, 'receipt_metadata' => ReceiptInvoice::snapshot(null, $this->config('vat'))];
        $items = [
            ['subtotal' => 112, 'discount_applied' => 11.2, 'tax_treatment_snapshot' => 'taxable'],
            ['subtotal' => 100, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'vat_exempt'],
            ['subtotal' => 50, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'zero_rated'],
        ];
        $result = ReceiptInvoice::data($sale, $items);
        $this->assertSame(['VATable sales' => 90.0, 'VAT amount (12%)' => 10.8,
            'VAT-exempt sales' => 100.0, 'Zero-rated sales' => 50.0], $result['tax_rows']);
        $this->assertEqualsWithDelta(250.8, array_sum($result['tax_rows']), 0.00001);
        unset($items[0]['tax_treatment_snapshot']);
        $this->assertFalse(ReceiptInvoice::data($sale, $items)['tax_recorded']);
        $sale['final_total'] = 1;
        $this->assertFalse(ReceiptInvoice::data($sale, $items)['tax_recorded']);
    }

    public function testRegistrationDiscountAndMedicineSnapshotsRemainStableAndEscapeOutput(): void
    {
        helper(['url', 'form']);
        $config = $this->config('non_vat');
        $config->tin = '123-456-789-000';
        $sale = ['id' => 42, 'invoice_no' => 'INV-original', 'final_total' => 80,
            'receipt_metadata' => ReceiptInvoice::snapshot(['discount_name' => 'Senior Citizen / PWD'], $config, 'SI-00000042'),
            'sale_date' => '2026-10-14 09:00:00', 'payment_method' => 'cash',
            'branch_name' => 'Main Branch', 'branch_address' => 'ADDRESS-ON-RECEIPT',
            'total_amount' => 100, 'discount_amount' => 20, 'amount_paid' => 100,
            'change_amount' => 20, 'status' => 'completed'];
        $items = [['product_name_snapshot' => 'Test medicine', 'quantity' => 1, 'price' => 100,
            'subtotal' => 100, 'discount_applied' => 20]
            + ReceiptInvoice::itemSnapshot(['generic_name' => '<script>alert(1)</script>', 'strength' => '500 mg'])];
        $config->tin = 'CHANGED';
        $result = ReceiptInvoice::data($sale, $items);
        $this->assertSame('123-456-789-000', $result['tin']);
        $this->assertSame('SI-00000042', $result['serial']);
        $this->assertSame(['Non-VAT sales' => 80.0, 'VAT charged' => 0.0], $result['tax_rows']);
        $html = view('cashier/sales/receipt', ['sale' => $sale, 'items' => $items], ['saveData' => false]);
        foreach (['SALES INVOICE', 'Senior Citizen / PWD', 'Strength: 500 mg', '&lt;script&gt;', 'SI-00000042'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('ADDRESS-ON-RECEIPT', $html);
        // The shared refund header still displays its normal branch address.
        $header = view('partials/receipt_header', ['businessName' => 'Store', 'businessType' => 'Drugstore',
            'documentType' => 'Refund slip', 'sale' => $sale], ['saveData' => false]);
        $this->assertStringContainsString('ADDRESS-ON-RECEIPT', $header);
    }

    public function testMigrationAndSerialAllocationPreserveHistoryAndRollBackFailedSales(): void
    {
        $db = new \CodeIgniter\Database\SQLite3\Connection(['database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBDebug' => true]);
        $db->initialize();
        try {
            foreach (['products', 'sales', 'sale_items'] as $table) {
                $db->query('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, original TEXT)');
                $db->table($table)->insert(['id' => 1, 'original' => 'Preserve me']);
            }
            require_once APPPATH . 'Database/Migrations/2026_10_14_000011_add_receipt_invoice_fields.php';
            $migration = new \App\Database\Migrations\AddReceiptInvoiceFields(new \CodeIgniter\Database\SQLite3\Forge($db));
            $migration->up();
            $migration->up();
            foreach (['products', 'sales', 'sale_items'] as $table) {
                $this->assertSame('Preserve me', $db->table($table)->get()->getRowArray()['original']);
            }
            $this->assertNull($db->table('products')->get()->getRowArray()['tax_treatment']);
            $this->assertNull($db->table('sales')->get()->getRowArray()['receipt_metadata']);
            $config = $this->config('non_vat');
            $config->serialPrefix = 'SI-';
            $config->serialStart = 100;
            $config->serialEnd = 101;
            $db->transBegin();
            $first = json_decode(ReceiptInvoice::capture($db, null, $config), true);
            $db->transRollback();
            $db->transBegin();
            $retry = json_decode(ReceiptInvoice::capture($db, null, $config), true);
            $db->transCommit();
            $this->assertSame('SI-00000100', $first['serial']);
            $this->assertSame($first['serial'], $retry['serial']);
            $db->transBegin();
            $second = json_decode(ReceiptInvoice::capture($db, null, $config), true);
            $db->transCommit();
            $this->assertSame('SI-00000101', $second['serial']);
            $db->transBegin();
            try {
                ReceiptInvoice::capture($db, null, $config);
                $this->fail('An exhausted series must not issue another serial.');
            } catch (\App\Libraries\InvoiceConfigurationException $error) {
                $this->assertStringContainsString('exhausted', $error->getMessage());
            } finally {
                $db->transRollback();
            }
        } finally {
            $db->close();
        }
    }
}
