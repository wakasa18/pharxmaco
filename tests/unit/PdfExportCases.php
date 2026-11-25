<?php

trait PdfExportCases
{
    public function testPdfSalesReconcilesRecordedVatAndPartialRefundAcrossGroupings(): void
    {
        $post = $this->statutoryCheckoutPost('pwd', ['items' => [1 => 2], 'amount_paid' => '224']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $item = $this->db->table('sale_items')->where('sale_id', 2)->get()->getRowArray();
        session()->set('refund_token_2', 'pdf-reconciliation');
        $this->controller(\App\Controllers\Cashier\SalesController::class, [
            '_refund_token' => 'pdf-reconciliation', 'reason' => 'Damaged test package', 'refund_method' => 'cash',
            'refund' => [$item['id'] => 1], 'return_condition' => [$item['id'] => 'damaged'],
        ])->refundPartial(2);
        $controller = $this->controller(\App\Controllers\Admin\ReportsController::class, []);
        $today = date('Y-m-d');
        foreach (['daily', 'weekly', 'monthly'] as $grouping) {
            $data = (new ReflectionMethod($controller, 'buildReportData'))->invoke($controller, $today, $today, $today, $today, '1', $grouping, $grouping);
            $summary = \App\Libraries\ReportPdf::reconciliation($data['salesSummary']);
            foreach (['original_sales' => 224, 'total_discount' => 40, 'total_vat_relief' => 24, 'total_refunds' => 80, 'net_sales' => 80, 'other_adjustments' => 0] as $field => $expected) {
                $this->assertEquals($expected, $summary[$field], $grouping . ' ' . $field);
            }
            foreach (['original_sales', 'total_vat_relief', 'total_refunds', 'net_sales'] as $field) {
                $this->assertEquals($summary[$field], array_sum(array_column($data['reportHistory'], $field)), $field);
            }
            $html = view('admin/reports/reports_pdf', $data + ['pdfMode' => 'full'], ['saveData' => false]);
            foreach (['Original sales', 'VAT relief', 'Refunds against sales', '₱224.00', '₱24.00', 'Top 10 Best-Selling Products', 'Latest 10 Sales'] as $text) $this->assertStringContainsString($text, $html);
            $screen = view('admin/reports/index', $data, ['saveData' => false]);
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $screen);
            $xpath = new DOMXPath($document);
            $metrics = [];
            foreach ($xpath->query('//dl[@aria-label="Sales reconciliation"]/div') as $metric) {
                $metrics[trim($xpath->evaluate('string(dt)', $metric))] = trim($xpath->evaluate('string(dd[1])', $metric));
            }
            $this->assertSame('₱224.00', $metrics['Original sales'] ?? null);
            $this->assertSame('₱40.00', $metrics['Discounts'] ?? null);
            $this->assertSame('₱24.00', $metrics['VAT exemption'] ?? null);
            $this->assertSame('₱80.00', $metrics['Refunds against sales'] ?? null);
            $this->assertStringContainsString('VAT exemption', $xpath->evaluate('string(//*[@class="rp-hero-sub"])'));
        }
        $legacy = \App\Libraries\ReportPdf::reconciliation(array_replace($summary, ['total_vat_relief' => 20]));
        $this->assertEquals(4, $legacy['other_adjustments']);
        $this->assertEquals(80, $this->db->table('refund_items')->where('sale_id', 2)->selectSum('refund_subtotal', 'total')->get()->getRowArray()['total']);
    }

    public function testSalesReconciliationExplainsScreenshotAndKeepsUnknownDifferencesSeparate(): void
    {
        $summary = ['original_sales' => 238862, 'gross_sales' => 238422, 'total_discount' => 779.02,
            'total_vat_relief' => 5.89, 'total_refunds' => 440, 'net_sales' => 237637.09, 'total_transactions' => 633];
        $html = view('admin/reports/_sales_summary', ['summary' => $summary, 'averageTransactionValue' => 375.41], ['saveData' => false]);
        foreach (['Original sales', 'VAT exemption', 'Refunds against sales', '₱238,862.00', '₱779.02', '₱5.89', '₱440.00', '₱237,637.09'] as $text) {
            $this->assertTrue(str_contains($html, $text), 'Missing reconciliation field: ' . $text);
        }
        $this->assertStringNotContainsString('Other adjustments', $html);
        foreach ([4, -4] as $adjustment) {
            $legacy = array_replace($summary, ['net_sales' => $summary['net_sales'] - $adjustment]);
            $html = view('admin/reports/_sales_summary', ['summary' => $legacy], ['saveData' => false]);
            $this->assertStringContainsString('Other adjustments', $html);
            $this->assertStringContainsString('₱' . number_format($adjustment, 2), $html);
            $this->assertStringContainsString('₱5.89', $html);
        }
        $empty = view('admin/reports/_sales_summary', ['summary' => []], ['saveData' => false]);
        $this->assertStringContainsString('₱0.00', $empty);
        $this->assertStringNotContainsString('Other adjustments', $empty);
    }

    public function testInventoryPdfsShowStableIdsUnitsTrueMovementAndRecordedReferences(): void
    {
        $this->retainedModels();
        $this->db->table('products')->where('id', 1)->update(['product_name' => 'Renamed <script> catalog item', 'unit' => 'tablet', 'sku' => 'TEST-SKU']);
        $remarks = 'Sold via POS Invoice QA-INV-001 ' . str_repeat('Recorded note for review. ', 12) . 'COMPLETE-NOTE-END';
        $this->db->table('stock_logs')->insert(['id' => 9, 'product_id' => 1, 'branch_id' => 1, 'user_id' => 1,
            'action_type' => 'stock_out', 'quantity' => 2, 'previous_stock' => 10, 'new_stock' => 8,
            'remarks' => $remarks, 'created_at' => date('Y-m-d H:i:s')]);
        $controller = $this->controller(\App\Controllers\StockLogsController::class, []);
        $logs = (new ReflectionMethod($controller, 'stockLogBuilder'))->invoke($controller, new \App\Models\StockLogModel($this->db),
            ['search' => '', 'branch_id' => '1', 'action_type' => '', 'date_from' => '', 'date_to' => ''])->get()->getResultArray();
        $data = \App\Libraries\ReportPdf::context('Stock Logs Report', 'Branch A', 'All dates', 'Test Staff');
        $html = view('stock_logs/pdf', $data + ['logs' => $logs, 'dateFrom' => '', 'dateTo' => '', 'search' => '', 'actionTypeLabel' => 'All Types'], ['saveData' => false]);
        foreach (['TEST-SKU / #1', 'tablet', '-2', '10 -&gt; 8', 'QA-INV-001', 'See LOG-9', 'COMPLETE-NOTE-END', 'current catalog values'] as $text) $this->assertStringContainsString($text, $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('QA-INV-001', \App\Libraries\ReportPdf::reference(['id' => 10, 'remarks' => 'Partial refund of QA-INV-001 - recorded return']));
        $reports = $this->controller(\App\Controllers\Admin\ReportsController::class, []);
        $today = date('Y-m-d'); $nearDate = date('Y-m-d', strtotime('+30 days'));
        $products = (new ReflectionMethod($reports, 'expiryProductsBuilder'))->invoke($reports, '1', 'all', $today, $nearDate)->get()->getResultArray();
        $html = view('admin/reports/expiry_report_pdf', $data + ['products' => $products, 'today' => $today, 'nearDate' => $nearDate,
            'nearExpiryDays' => 30, 'filterStatusLabel' => 'All Statuses', 'summary' => ['total' => 1, 'good' => 1]], ['saveData' => false]);
        foreach (['TEST-SKU', '#1', 'tablet', str_repeat('a', 32), 'Lot: Not recorded', 'Good', 'Days left'] as $text) $this->assertStringContainsString($text, $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
    }
}
