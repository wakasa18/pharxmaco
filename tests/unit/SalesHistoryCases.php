<?php

trait SalesHistoryCases
{
    private function salesHistoryPage(array $query = []): DOMXPath
    {
        helper(['url', 'form']);
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, []);
        service('request')->setMethod('GET');
        service('request')->setGlobal('get', $query);
        \Config\Services::resetSingle('renderer');
        $html = $controller->history();
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        return new DOMXPath($dom);
    }

    private function salesHistoryInvoices(DOMXPath $page): array
    {
        return array_map(static fn ($node) => trim($node->textContent), iterator_to_array(
            $page->query('//table[@aria-label="Sales history"]/tbody/tr/td[1]/strong')
        ));
    }

    public function testSalesHistoryRangeFindsOlderSalesOutsideLatestLimit(): void
    {
        $recent = [];
        for ($id = 2; $id <= 1003; $id++) {
            $recent[] = ['id' => $id, 'invoice_no' => 'RECENT-' . $id, 'user_id' => 1, 'branch_id' => 1,
                'final_total' => 10, 'payment_method' => 'cash', 'status' => 'completed', 'sale_date' => '2026-10-20 12:00:00'];
        }
        $this->db->table('sales')->insertBatch($recent);
        $latest = $this->salesHistoryPage();
        $this->assertCount(1000, $this->salesHistoryInvoices($latest));
        $this->assertNotContains('TEST-1', $this->salesHistoryInvoices($latest));
        $this->assertStringContainsString('latest 1,000 sales', $latest->query('//*[@id="sales-history-note"]')->item(0)->textContent);
        $older = $this->salesHistoryPage(['date_from' => '2026-09-14', 'date_to' => '2026-09-14']);
        $this->assertSame(['TEST-1'], $this->salesHistoryInvoices($older));
        $this->assertStringContainsString('Showing all 1 sale in this date range', $older->query('//*[@id="sales-history-note"]')->item(0)->textContent);
        $this->assertSame('2026-09-14', $older->query('//*[@id="history-date-from"]')->item(0)->getAttribute('value'));
    }

    public function testSalesHistoryRangeIncludesWholeEndDayAndSupportsOpenBounds(): void
    {
        foreach ([2 => '2026-09-14 00:00:00', 3 => '2026-09-14 23:59:59', 4 => '2026-09-15 00:00:00'] as $id => $date) {
            $this->db->table('sales')->insert(['id' => $id, 'invoice_no' => 'BOUND-' . $id, 'user_id' => 1, 'branch_id' => 1,
                'final_total' => 10, 'payment_method' => 'cash', 'status' => 'completed', 'sale_date' => $date]);
        }
        $this->assertSame(['BOUND-3', 'BOUND-2', 'TEST-1'], $this->salesHistoryInvoices($this->salesHistoryPage(['date_to' => '2026-09-14'])));
        $this->assertSame(['BOUND-4'], $this->salesHistoryInvoices($this->salesHistoryPage(['date_from' => '2026-09-15'])));
        $this->assertCount(4, $this->salesHistoryInvoices($this->salesHistoryPage()));
    }

    public function testSalesHistoryRejectsInvalidOrReversedRangesWithoutLoadingOtherSales(): void
    {
        foreach ([['date_from' => '2026-10-20', 'date_to' => '2026-09-14'], ['date_from' => '2026-02-30'],
            ['date_to' => 'not-a-date'], ['date_from' => '0000-01-01'], ['date_to' => '2026-9-14']] as $range) {
            $page = $this->salesHistoryPage($range);
            $this->assertSame([], $this->salesHistoryInvoices($page));
            $this->assertSame(1, $page->query('//*[@id="sales-history-error" and @role="alert"]')->length);
            $this->assertStringContainsString('No transactions loaded', $page->query('//*[@id="sales-history-note"]')->item(0)->textContent);
        }
    }

    public function testSalesHistoryDateRangeRetainsCashierBranchRestriction(): void
    {
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Other branch', 'status' => 'active']);
        $this->db->table('sales')->insert(['id' => 2, 'invoice_no' => 'OTHER-BRANCH', 'user_id' => 1, 'branch_id' => 2,
            'final_total' => 20, 'payment_method' => 'cash', 'status' => 'completed', 'sale_date' => '2026-09-14 12:00:00']);
        session()->set('role', 'cashier');
        $query = ['date_from' => '2026-09-14', 'date_to' => '2026-09-14', 'branch_id' => '2'];
        $this->assertSame(['TEST-1'], $this->salesHistoryInvoices($this->salesHistoryPage($query)));
        session()->set('role', 'admin');
        $this->assertSame(['OTHER-BRANCH'], $this->salesHistoryInvoices($this->salesHistoryPage($query)));
    }

    public function testSalesHistoryAlwaysProvidesSingleRangeAndKeepsLinkedFilters(): void
    {
        $page = $this->salesHistoryPage(['q' => 'TEST', 'payment_method' => 'cash', 'status' => 'completed']);
        $this->assertSame(2, $page->query('//*[@id="sales-history-range"]//input[@type="date"]')->length);
        $this->assertSame('off', $page->query('//*[@id="sales-history-range"]')->item(0)->getAttribute('data-auto-filter'));
        $this->assertSame('TEST', $page->query('//*[@id="sales-history-range"]/input[@name="q"]')->item(0)->getAttribute('value'));
        $this->assertSame('off', $page->query('//table[@aria-label="Sales history"]')->item(0)->getAttribute('data-dt-date-filter'));
        $this->assertSame(site_url('cashier/sales/history'), $page->query('//*[@class="sales-history-actions"]/a')->item(0)->getAttribute('href'));
        $this->assertStringContainsString('Quick search filters loaded transactions only', $page->query('//*[@id="sales-history-note"]')->item(0)->textContent);
        $empty = $this->salesHistoryPage(['date_to' => '2020-01-01']);
        $this->assertSame([], $this->salesHistoryInvoices($empty));
        $this->assertSame(2, $empty->query('//*[@id="sales-history-range"]//input[@type="date"]')->length);
    }
}
