<?php

/** Sale-cohort cost recovery and generated export attachment regressions. */
trait ReportRepairCases
{
    use \CodeIgniter\Test\FeatureTestTrait;
    private function reportRepairController(array $get = []): \App\Controllers\Admin\ReportsController
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS report_exports (id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER, branch_id INTEGER, export_type TEXT, date_from TEXT, date_to TEXT,
            gross_sales NUMERIC, net_sales NUMERIC, total_discount NUMERIC, cogs NUMERIC,
            gross_profit NUMERIC, total_transactions INTEGER, created_at TEXT)');
        $controller = $this->controller(\App\Controllers\Admin\ReportsController::class, []);
        service('request')->setMethod('GET');
        service('request')->setGlobal('get', $get + ['report_date_from' => '2026-09-14', 'report_date_to' => '2026-09-14',
            'forecast_date_from' => '2026-09-14', 'forecast_date_to' => '2026-09-14', 'branch_id' => '1']);
        return $controller;
    }

    private function reportRepairData(string $grouping = 'daily', string $branch = '1'): array
    {
        $controller = $this->reportRepairController();
        return (new ReflectionMethod($controller, 'buildReportData'))->invoke($controller,
            '2026-09-14', '2026-09-14', '2026-09-14', '2026-09-14', $branch, $grouping, $grouping);
    }

    private function reportRepairArtifact(string $name, string $body): void
    {
        $dir = WRITEPATH . 'repairs-20261028/reports/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . $name, $body);
    }

    public function testReportRepairReleasedQuarantineRecoversCostOnceInAllGroupings(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
        $refund = $this->row('refund_items'); $item = $this->row('sale_items');
        $before = $this->reportRepairData();
        $this->assertEquals(20, $before['profitSummary']['cogs']);
        $this->assertEquals(10, $before['profitSummary']['gross_profit']);
        $record = $service->item(1, 1);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput(), \App\Libraries\Quarantine::revision($record)));
        $this->assertFalse($service->resolve(1, 1, $this->quarantineInput(), \App\Libraries\Quarantine::revision($record)));
        foreach (['daily', 'weekly', 'monthly'] as $grouping) foreach (['1', ''] as $branch) {
            $data = $this->reportRepairData($grouping, $branch);
            $this->assertEquals(30, $data['salesSummary']['net_sales']);
            $this->assertEquals(12, $data['profitSummary']['cogs']);
            $this->assertEquals(18, $data['profitSummary']['gross_profit']);
            $this->assertEquals(12, array_sum(array_column($data['reportHistory'], 'cogs')));
            $this->assertEquals(18, array_sum(array_column($data['reportHistory'], 'gross_profit')));
        }
        $this->assertSame($refund, $this->row('refund_items'));
        $this->assertSame($item, $this->row('sale_items'));
        $this->assertSame(1, $this->db->table('quarantine_resolutions')->countAllResults());
        // Resolution in October restates September's sale cohort; no October cost-only journal is invented.
        $controller = $this->reportRepairController();
        $october = (new ReflectionMethod($controller, 'buildReportData'))->invoke($controller,
            '2026-10-01', '2026-10-31', '2026-10-01', '2026-10-31', '1');
        $this->assertEquals(0, $october['profitSummary']['cogs']);
    }

    public function testReportRepairMixedReturnsRecoverOnlyResellableAndReleasedCosts(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
        $refund = $this->row('refund_items');
        foreach ([2 => 'resellable', 3 => 'damaged'] as $id => $condition) {
            $this->db->table('refund_items')->insert(array_replace($refund, ['id' => $id,
                'return_condition' => $condition, 'quantity_refunded' => 1, 'refund_subtotal' => 10]));
        }
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput(), \App\Libraries\Quarantine::revision($service->item(1, 1))));
        $data = $this->reportRepairData();
        $this->assertEquals(10, $data['salesSummary']['net_sales']);
        $this->assertEquals(8, $data['profitSummary']['cogs']);
        $this->assertEquals(2, $data['profitSummary']['gross_profit']);
        $this->assertEquals(8, array_sum(array_column($data['reportHistory'], 'cogs')));
        $this->assertSame('quarantined', $this->row('refund_items')['return_condition']);
    }

    public function testReportRepairUnreleasedAndDamagedRefundsKeepTheirCostLoss(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('sales')->where('id', 1)->update(['status' => 'refunded']);
        $this->db->table('refund_items')->where('id', 1)->update(['quantity_refunded' => 5, 'refund_subtotal' => 50]);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput('damaged'), \App\Libraries\Quarantine::revision($service->item(1, 1))));
        $data = $this->reportRepairData();
        $this->assertEquals(0, $data['salesSummary']['net_sales']);
        $this->assertEquals(20, $data['profitSummary']['cogs']);
        $this->assertEquals(-20, $data['profitSummary']['gross_profit']);
        $this->assertEquals(-20, array_sum(array_column($data['reportHistory'], 'gross_profit')));
    }

    public function testReportRepairLegacySchemaWithoutQuarantineStillReportsResellableCosts(): void
    {
        $this->db->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
        $this->db->table('refund_items')->insert(['sale_id' => 1, 'sale_item_id' => 1, 'quantity_refunded' => 2,
            'refund_subtotal' => 20, 'return_condition' => 'resellable']);
        $data = $this->reportRepairData();
        $this->assertEquals(12, $data['profitSummary']['cogs']);
        $this->assertEquals(18, $data['profitSummary']['gross_profit']);
        $this->assertFalse($this->db->tableExists('quarantine_resolutions'));
    }

    public function testReportRepairCsvGeneratesAttachmentAndRecordsRecoveredTotalsOnce(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput(), \App\Libraries\Quarantine::revision($service->item(1, 1))));
        foreach ([1, 2] as $attempt) {
            $response = $this->reportRepairController(['export_request' => str_repeat('a', 32)])->exportExcel();
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('text/csv', $response->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('attachment; filename="sales_report_', $response->getHeaderLine('Content-Disposition'));
            $this->assertStringContainsString('.csv"', $response->getHeaderLine('Content-Disposition'));
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getBody());
            $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        }
        $csv = $response->getBody(); $stream = fopen('php://temp', 'w+b'); fwrite($stream, substr($csv, 3)); rewind($stream);
        $metrics = []; while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $this->assertCount(8, $row); if (in_array($row[0], ['Net Sales', 'Product Cost', 'Gross Profit'], true)) $metrics[$row[0]] = $row[1];
        } fclose($stream);
        $this->assertSame(['Net Sales' => '30.00', 'Product Cost' => '12.00', 'Gross Profit' => '18.00'], $metrics);
        $this->assertSame(1, $this->db->table('report_exports')->countAllResults());
        $history = $this->db->table('report_exports')->get()->getRowArray();
        $this->assertEquals(12, $history['cogs']); $this->assertEquals(18, $history['gross_profit']);
        $this->reportRepairArtifact('recovered-cost.csv', $csv);
    }

    public function testReportRepairCsvGenerationFailureDoesNotCreateHistoryOrConsumeReceipt(): void
    {
        $source = $this->reportRepairController(['export_request' => str_repeat('b', 32)]);
        $controller = $this->getMockBuilder(\App\Controllers\Admin\ReportsController::class)->disableOriginalConstructor()
            ->onlyMethods(['renderReportCsv'])->getMock();
        foreach (['db', 'reportExportModel', 'branchModel'] as $name) {
            $prop = new ReflectionProperty($source, $name); $prop->setValue($controller, $prop->getValue($source));
        }
        $controller->initController(service('request'), service('response'), service('logger'));
        $controller->method('renderReportCsv')->willThrowException(new RuntimeException('Synthetic export stream failure'));
        try { $controller->exportExcel(); $this->fail('Expected generation failure.'); }
        catch (RuntimeException $e) { $this->assertSame('Synthetic export stream failure', $e->getMessage()); }
        $this->assertSame(0, $this->db->table('report_exports')->countAllResults());
        $this->assertEmpty(session('report_export_receipts'));
        $this->assertSame(200, $this->reportRepairController(['export_request' => str_repeat('b', 32)])->exportExcel()->getStatusCode());
        $this->assertSame(1, $this->db->table('report_exports')->countAllResults());
    }

    public function testReportRepairFullAndSummaryPdfGenerateDistinctAttachmentsAndHistory(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput(), \App\Libraries\Quarantine::revision($service->item(1, 1))));
        foreach (['full' => 'sales_report_', 'summary' => 'sales_summary_'] as $mode => $prefix) {
            $response = $this->reportRepairController(['pdf_mode' => $mode, 'export_request' => str_repeat('c', 32)])->exportPdf();
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('attachment; filename="' . $prefix, $response->getHeaderLine('Content-Disposition'));
            $this->assertStringStartsWith('%PDF-', $response->getBody());
            $this->reportRepairArtifact($mode . '.pdf', $response->getBody());
        }
        $this->assertSame(2, $this->db->table('report_exports')->countAllResults());
        foreach ($this->db->table('report_exports')->get()->getResultArray() as $history) {
            $this->assertEquals(12, $history['cogs']); $this->assertEquals(18, $history['gross_profit']);
        }
    }

    public function testReportRepairActualRoutesReturnAttachmentsAndRejectGuests(): void
    {
        $this->reportRepairController();
        $identity = session()->get();
        $query = http_build_query(['branch_id' => '1', 'report_date_from' => '2026-09-14', 'report_date_to' => '2026-09-14',
            'forecast_date_from' => '2026-09-14', 'forecast_date_to' => '2026-09-14', 'export_request' => str_repeat('d', 32)]);
        foreach ([['admin/reports/export-excel?', 'text/csv', 'sales_report_', "\xEF\xBB\xBF"],
            ['admin/reports/pdf?', 'application/pdf', 'sales_report_', '%PDF-'],
            ['admin/reports/pdf?pdf_mode=summary&', 'application/pdf', 'sales_summary_', '%PDF-']] as [$path, $type, $prefix, $signature]) {
            $result = $this->withSession($identity)->get($path . $query);
            $result->assertStatus(200);
            $response = $result->response();
            $this->assertStringContainsString($type, $response->getHeaderLine('Content-Type'));
            $this->assertStringContainsString('attachment; filename="' . $prefix, $response->getHeaderLine('Content-Disposition'));
            $this->assertStringStartsWith($signature, $response->getBody());
            $identity = session()->get();
        }
        $this->assertSame(3, $this->db->table('report_exports')->countAllResults());
        $result = $this->withSession([])->get('admin/reports/export-excel?' . $query);
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
        $this->assertSame(3, $this->db->table('report_exports')->countAllResults());
    }

}
