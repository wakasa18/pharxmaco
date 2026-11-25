<?php

use App\Libraries\ReceiptInvoice;
use App\Libraries\StatutoryDiscount;
use CodeIgniter\Test\CIUnitTestCase;

final class StatutoryDiscountTest extends CIUnitTestCase
{
    private function input(): array
    {
        return ['beneficiary_name' => 'Test Beneficiary', 'beneficiary_id' => 'TEST-ID-123', 'benefit_verified' => '1', 'benefit_products' => [1 => '1']];
    }

    public function testSharedBrowserCasesUseVatExclusiveBaseWithoutDeductingVatTwice(): void
    {
        foreach (json_decode(file_get_contents(TESTPATH . 'fixtures/statutory-discount-cases.json'), true) as $case) {
            $config = new \Config\ReceiptInvoice();
            $config->taxStatus = $case['status'];
            $items = [];
            $selected = [];
            foreach ($case['lines'] as $i => $line) {
                $items[$i + 1] = ['subtotal' => $line['subtotal'], 'tax_treatment' => $line['taxTreatment'], 'senior_pwd_eligible' => 1];
                $selected[$i + 1] = '1';
            }
            foreach (array_keys(StatutoryDiscount::OPTIONS) as $kind) {
                $quote = StatutoryDiscount::quote($kind, $items, array_replace($this->input(), ['benefit_products' => $selected]), $config);
                $this->assertSame((float) $case['discount'], $quote['discount']);
                $this->assertSame((float) $case['vatExemption'], $quote['vat_exemption']);
                $this->assertSame((int) round($quote['discount'] * 100), array_sum(array_map(static fn ($item) => (int) round($item['discount'] * 100), $quote['items'])));
            }
        }
    }

    public function testMixedInvoiceSeparatesRegularTaxableSalesFromBeneficiaryVatExemptSales(): void
    {
        $config = new \Config\ReceiptInvoice();
        $config->taxStatus = 'vat';
        $quote = StatutoryDiscount::quote('pwd', [1 => ['subtotal' => 224, 'tax_treatment' => 'taxable', 'senior_pwd_eligible' => 1]], $this->input(), $config);
        $sale = ['final_total' => 272, 'receipt_metadata' => ReceiptInvoice::snapshot(['discount_name' => 'PWD (20%)'], $config, null, $quote['beneficiary'])];
        $items = [
            ['subtotal' => 224, 'discount_applied' => 40, 'vat_exemption_amount' => 24, 'statutory_discount_kind' => 'pwd', 'tax_treatment_snapshot' => 'taxable'],
            ['subtotal' => 112, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'taxable'],
        ];
        $invoice = ReceiptInvoice::data($sale, $items);
        $this->assertTrue($invoice['tax_recorded']);
        $this->assertSame(160.0, $invoice['tax_rows']['VAT-exempt sales']);
        $this->assertSame(100.0, $invoice['tax_rows']['VATable sales']);
        $this->assertSame(12.0, $invoice['tax_rows']['VAT amount (12%)']);
        $this->assertSame(24.0, $invoice['vat_exemption']);
        $this->assertSame('Test Beneficiary', $invoice['beneficiary']['name']);
        $this->assertSame(160.0, StatutoryDiscount::netItem($items[0]));
    }

    public function testInvalidBeneficiaryUnverifiedEligibilityAndForgedProductsAreRejected(): void
    {
        $config = new \Config\ReceiptInvoice();
        $config->taxStatus = 'vat';
        $items = [1 => ['subtotal' => 112, 'tax_treatment' => 'taxable', 'senior_pwd_eligible' => 1]];
        foreach ([
            ['beneficiary_name' => ''], ['beneficiary_id' => ''], ['beneficiary_id' => ['forged']],
            ['benefit_verified' => '0'], ['benefit_products' => []], ['benefit_products' => [2 => '1']],
            ['benefit_products' => [1 => '1', '01' => '1']], ['benefit_products' => [1 => 'yes']],
        ] as $change) {
            try { StatutoryDiscount::quote('senior_citizen', $items, array_replace($this->input(), $change), $config); $this->fail('Invalid input accepted.'); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        foreach ([['senior_pwd_eligible' => null], ['senior_pwd_eligible' => 0], ['tax_treatment' => null], ['subtotal' => 0]] as $change) {
            try { StatutoryDiscount::quote('pwd', [1 => array_replace($items[1], $change)], $this->input(), $config); $this->fail('Unqualified product accepted.'); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $config->taxStatus = '';
        $this->expectException(\InvalidArgumentException::class);
        StatutoryDiscount::quote('pwd', $items, $this->input(), $config);
    }

    public function testMigrationIsRepeatableAndKeepsHistoricalAmounts(): void
    {
        $db = \Config\Database::connect(['database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => ''], false);
        try {
            $db->query('CREATE TABLE products (id INTEGER PRIMARY KEY, product_name TEXT)');
            $db->query('CREATE TABLE sale_items (id INTEGER PRIMARY KEY, subtotal NUMERIC, discount_applied NUMERIC)');
            $db->query("INSERT INTO products VALUES (1, 'Existing medicine')");
            $db->query('INSERT INTO sale_items VALUES (1, 112, 22.4)');
            require_once APPPATH . 'Database/Migrations/2026_10_14_000013_add_statutory_discount_fields.php';
            $migration = new \App\Database\Migrations\AddStatutoryDiscountFields(new \CodeIgniter\Database\SQLite3\Forge($db));
            $migration->up(); $migration->up();
            $this->assertNull($db->table('products')->get()->getRowArray()['senior_pwd_eligible']);
            $item = $db->table('sale_items')->get()->getRowArray();
            $this->assertSame(112.0, (float) $item['subtotal']);
            $this->assertSame(22.4, (float) $item['discount_applied']);
            $this->assertSame(0.0, (float) $item['vat_exemption_amount']);
            $this->assertNull($item['statutory_discount_kind']);
        } finally { $db->close(); }
    }
}
