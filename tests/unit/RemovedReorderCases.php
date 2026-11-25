<?php

/** Removed product reorder planning, while preserving sales forecasts and threshold alerts. */
trait RemovedReorderCases
{
    private function removedReorderController(array $post = []): \App\Controllers\Admin\ReportsController
    {
        return $this->controller(\App\Controllers\Admin\ReportsController::class, $post);
    }

    private function removedReorderData(string $branch = '1'): array
    {
        $controller = $this->removedReorderController();
        return (new ReflectionMethod($controller, 'buildReportData'))->invoke($controller,
            '2026-09-14', '2026-09-14', '2026-09-14', '2026-09-14', $branch, 'daily', 'daily');
    }

    public function testRemovedReorderReportReadsKeepSalesForecastAndManualLowStockCount(): void
    {
        $inventory = $this->row('branch_products');
        $data = $this->removedReorderData();
        $this->assertArrayNotHasKey('reorderForecast', $data);
        $this->assertArrayNotHasKey('reorderCoverageDays', $data);
        $this->assertSame(1, $data['lowStockCount']);
        $this->assertCount(7, $data['futureForecast']);
        $this->assertGreaterThan(0, $data['nextForecastValue']);
        $this->assertSame(0, $this->db->table('forecasting_data')->countAllResults());
        $this->assertSame($inventory, $this->row('branch_products'));
    }

    public function testRemovedReorderLowStockCountsRespectBranchAndActiveInventory(): void
    {
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Second Branch', 'status' => 'active']);
        $this->db->table('branch_products')->insert(['id' => 2, 'product_id' => 1, 'branch_id' => 2, 'stock' => 0,
            'reorder_level' => 5, 'price' => 10, 'status' => 'active']);
        $this->assertSame(2, $this->removedReorderData('')['lowStockCount']);
        $this->assertSame(1, $this->removedReorderData('2')['lowStockCount']);
        $this->db->table('branch_products')->where('id', 2)->update(['status' => 'inactive']);
        $this->assertSame(1, $this->removedReorderData('')['lowStockCount']);
        $this->assertSame(0, $this->removedReorderData('2')['lowStockCount']);
        $this->db->table('branch_products')->where('id', 1)->update(['stock' => 101]);
        $this->assertSame(0, $this->removedReorderData('1')['lowStockCount']);
    }

    public function testRemovedReorderUpdateForecastSavesOnlySalesAndKeepsHistoricalProductRows(): void
    {
        $this->db->table('forecasting_data')->insert(['id' => 50, 'product_id' => 1, 'forecast_month' => '2026-09-01',
            'predicted_quantity' => 77, 'predicted_revenue' => 770, 'method_used' => 'Manual Holt b=1 2026-09-01..2026-09-14 daily a=0.3 b=0.2',
            'generated_at' => '2026-09-14 12:00:00']);
        $historical = $this->db->table('forecasting_data')->where('id', 50)->get()->getRowArray();
        $inventory = $this->row('branch_products');
        // A removed product-snapshot path would fail; saving sales must still work.
        $this->db->query("CREATE TRIGGER reject_removed_product_snapshot BEFORE INSERT ON forecasting_data WHEN NEW.product_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'product snapshots removed'); END");
        $post = ['branch_id' => '1', 'report_date_from' => '2026-09-14', 'report_date_to' => '2026-09-14',
            'forecast_date_from' => '2026-09-14', 'forecast_date_to' => '2026-09-14', 'forecast_type' => 'daily'];
        $this->assertSame(303, $this->removedReorderController($post)->updateForecast()->getStatusCode());
        $this->assertStringContainsString('Saved 7 sales forecast periods.', session()->getFlashdata('forecast_success'));
        $this->assertSame(8, $this->db->table('forecasting_data')->countAllResults());
        $this->assertSame(7, $this->db->table('forecasting_data')->where('product_id', null)->countAllResults());
        $this->assertSame($historical, $this->db->table('forecasting_data')->where('id', 50)->get()->getRowArray());
        $this->assertSame($inventory, $this->row('branch_products'));
        $log = (new ReflectionMethod($this->removedReorderController(), 'getForecastingDataLog'))->invoke($this->removedReorderController());
        $this->assertCount(7, $log);
        foreach ($log as $row) $this->assertNull($row['product_id']);
    }

    public function testRemovedReorderNotificationsKeepThresholdAlertsWithoutSuggestedQuantities(): void
    {
        $summary = (new \App\Libraries\TopbarNotificationService($this->db))->getSummary('admin');
        $this->assertSame(1, $summary['low_stock_total']);
        $this->assertCount(1, $summary['low_stock_products']);
        $product = $summary['low_stock_products'][0];
        $this->assertArrayNotHasKey('reorder_forecast', $product);
        $this->assertArrayNotHasKey('forecast_has_demand', $product);
        $this->assertSame(100, (int) $product['reorder_level']);
        $html = view('layouts/low_stock_modal', ['notificationData' => $summary], ['saveData' => false]);
        $this->assertStringContainsString('Low-stock products', $html);
        $this->assertStringContainsString('Reorder level', $html);
        $this->assertStringNotContainsString('Reorder forecast', $html);
        $this->assertStringNotContainsString('Suggested quantity', $html);
        $this->assertSame(0, $this->db->table('forecasting_data')->countAllResults());
    }

    public function testRemovedReorderReportScreenAndCsvRetainSalesWithoutSuggestionControls(): void
    {
        $data = $this->removedReorderData();
        $html = view('admin/reports/index', $data + ['exportHistory' => []], ['saveData' => false]);
        foreach (['Restock Suggestions', 'Restock suggestions', 'Suggested Order', 'data-open-restock', 'restockCurrent', 'Reorder forecast', 'low-stock product estimates'] as $removed) {
            $this->assertStringNotContainsString($removed, $html);
        }
        $this->assertStringContainsString('Saved Sales Forecasts', $html);
        $this->assertStringContainsString('Low-stock products', $html);
        $this->assertStringContainsString('forecastChart', $html);
        $controller = $this->removedReorderController();
        $csv = (new ReflectionMethod($controller, 'renderReportCsv'))->invoke($controller, $data);
        $this->assertStringNotContainsString('RESTOCK SUGGESTIONS', $csv);
        $this->assertStringNotContainsString('Suggested Order Quantity', $csv);
        $this->assertStringContainsString('Low-Stock Products', $csv);
        $this->assertStringContainsString('ESTIMATED FUTURE SALES', $csv);
    }

    public function testRemovedReorderFullAndSummaryTemplatesHaveNoSuggestionSections(): void
    {
        $data = $this->removedReorderData();
        foreach (['full', 'summary'] as $mode) {
            $html = view('admin/reports/reports_pdf', $data + ['pdfMode' => $mode], ['saveData' => false]);
            $this->assertStringNotContainsString('Restock Suggestions', $html);
            $this->assertStringNotContainsString('restock suggestions', $html);
            $this->assertStringNotContainsString('Suggested', $html);
            $this->assertStringContainsString('Sales Reconciliation', $html);
            if ($mode === 'full') $this->assertStringContainsString('Estimated Future Sales', $html);
        }
    }
}
