<?php

/** Product filters use retained branch inventory, not the shared master balance. */
trait ProductListFilterCases
{
    private function productListFilterFixture(): void
    {
        helper(['url', 'form']);
        $this->retainedModels();
        $this->db->table('products')->where('id', 1)->update(['product_name' => 'Filter Item 1', 'category_id' => 1, 'manufacturer' => 'Acme', 'medicine_type' => 'generic', 'stock' => 999]);
        $this->db->table('branch_products')->where('id', 1)->update(['stock' => 0, 'reorder_level' => 5]);
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Other Branch', 'status' => 'active']);
        $makers = [2 => '  Acme  ', 3 => 'Acme', 4 => 'Unilab', 5 => "O'Connor & Sons / Pharma", 6 => 'Other Branch Maker', 7 => 'Trashed Maker', 8 => 'Removed Branch Maker', 9 => 'Acme'];
        foreach ($makers as $id => $maker) {
            $this->seedExchangeReplacement($id, 12);
            $this->db->table('products')->where('id', $id)->update(['product_name' => 'Filter Item ' . $id, 'sku' => 'FILTER-' . $id, 'manufacturer' => $maker,
                'category_id' => 1, 'stock' => 999, 'medicine_type' => in_array($id, [2, 4, 9], true) ? 'branded' : 'generic']);
        }
        foreach ([2 => [3, 5], 3 => [5, 5], 4 => [6, 5], 5 => [12, 0], 6 => [2, 5], 9 => [1, 1]] as $id => [$stock, $level]) {
            $this->db->table('branch_products')->where('id', $id)->update(['stock' => $stock, 'reorder_level' => $level]);
        }
        $this->db->table('branch_products')->where('id', 4)->update(['expiration_date' => '2000-01-01']);
        $this->db->table('branch_products')->where('id', 6)->update(['branch_id' => 2]);
        $this->db->table('products')->where('id', 7)->update(['is_deleted' => 1, 'deleted_at' => '2026-10-01 10:00:00']);
        $this->db->table('branch_products')->where('id', 8)->update(['is_deleted' => 1, 'deleted_at' => '2026-10-01 10:00:00']);
        $this->db->table('branch_products')->where('id', 9)->update(['status' => 'inactive']);
    }

    private function productListFilterPage(array $get): string
    {
        $controller = $this->controller(\App\Controllers\Products::class, []);
        service('request')->setMethod('GET');
        service('request')->setGlobal('get', $get);
        return $controller->index();
    }

    private function productListFilterDom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($document);
    }

    private function productListFilterAjax(array $get): array
    {
        $controller = $this->controller(\App\Controllers\Products::class, []);
        service('request')->setMethod('GET');
        service('request')->setGlobal('get', $get);
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        try {
            $response = $controller->index();
            $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
            return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            service('request')->removeHeader('X-Requested-With');
        }
    }

    private function productListAjaxIds(array $data): array
    {
        return $this->productListFilterIds('<table aria-label="Products"><tbody>' . $data['rows_html'] . '</tbody></table>');
    }

    private function productListFilterIds(string $html): array
    {
        $ids = [];
        foreach ($this->productListFilterDom($html)->query('//table[@aria-label="Products"]/tbody/tr[count(td)=14]/td[1]') as $node) $ids[] = (int) trim($node->textContent);
        sort($ids);
        return $ids;
    }

    private function productListFilterSnapshot(): array
    {
        return array_map(fn ($table) => $this->db->table($table)->orderBy('id')->get()->getResultArray(), ['products', 'branch_products', 'stock_logs']);
    }

    public function testProductListFiltersUseBranchQuantityAndInclusiveReorderBoundary(): void
    {
        $this->productListFilterFixture();
        $before = $this->productListFilterSnapshot();
        foreach (['' => [1, 2, 3, 4, 5, 9], 'in' => [2, 3, 4, 5, 9], 'low' => [1, 2, 3, 9], 'out' => [1], 'healthy' => [4, 5]] as $filter => $expected) {
            $this->assertSame($expected, $this->productListFilterIds($this->productListFilterPage(['branch_id' => '1', 'stock_filter' => $filter])), $filter);
        }
        $this->assertSame($before, $this->productListFilterSnapshot());
    }

    public function testProductListManufacturerFilterMatchesExactTrimmedNamesAndEscapesPunctuation(): void
    {
        $this->productListFilterFixture();
        $this->assertSame([1, 2, 3, 9], $this->productListFilterIds($this->productListFilterPage(['branch_id' => '1', 'manufacturer' => 'Acme'])));
        $name = "O'Connor & Sons / Pharma";
        $html = $this->productListFilterPage(['branch_id' => '1', 'manufacturer' => $name]);
        $this->assertSame([5], $this->productListFilterIds($html));
        $dom = $this->productListFilterDom($html);
        $this->assertSame($name, $dom->evaluate('string(//select[@name="manufacturer"]/option[@selected]/@value)'));
        $this->assertSame(1, $dom->query('//select[@name="manufacturer"]/option[@value="Acme"]')->length);
        $this->assertSame(0, $dom->query('//select[@name="manufacturer"]/option[@value="Trashed Maker" or @value="Removed Branch Maker" or @value="Other Branch Maker"]')->length);
    }

    public function testProductListFiltersCombineAndKeepManufacturerChoicesIndependentOfResults(): void
    {
        $this->productListFilterFixture();
        $html = $this->productListFilterPage(['branch_id' => '1', 'stock_filter' => 'in', 'manufacturer' => 'Acme', 'medicine_type' => 'branded', 'status' => 'active', 'category_id' => '1', 'keyword' => 'FILTER-2']);
        $this->assertSame([2], $this->productListFilterIds($html));
        $dom = $this->productListFilterDom($html);
        $this->assertSame('in', $dom->evaluate('string(//select[@name="stock_filter"]/option[@selected]/@value)'));
        $this->assertSame('branded', $dom->evaluate('string(//select[@name="medicine_type"]/option[@selected]/@value)'));
        $this->assertSame(1, $dom->query('//select[@name="manufacturer"]/option[@value="Unilab"]')->length);
        $this->assertSame(0, $dom->query('//form[@aria-label="Product filters"]/input[@type="hidden" and @name="stock_filter"]')->length);
    }

    public function testProductListManufacturerChoicesAndRowsStayInCashiersAssignedBranch(): void
    {
        $this->productListFilterFixture();
        session()->set(['role' => 'cashier', 'branch_id' => 1]);
        $html = $this->productListFilterPage(['branch_id' => '2']);
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListFilterIds($html));
        $dom = $this->productListFilterDom($html);
        $this->assertSame('1', $dom->evaluate('string(//form[@aria-label="Product filters"]/input[@name="branch_id"]/@value)'));
        $this->assertSame(0, $dom->query('//select[@name="manufacturer"]/option[@value="Other Branch Maker"]')->length);
        $this->assertSame([4], $this->productListFilterIds($this->productListFilterPage(['branch_id' => '2', 'manufacturer' => 'Unilab', 'stock_filter' => 'in'])));
    }

    public function testProductListFiltersHandleMalformedInputsWithoutExpandingAnExactManufacturerMatch(): void
    {
        $this->productListFilterFixture();
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListFilterIds($this->productListFilterPage(['branch_id' => '1', 'manufacturer' => ['Acme'], 'stock_filter' => ['in']])));
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListFilterIds($this->productListFilterPage(['branch_id' => '1', 'stock_filter' => 'unknown'])));
        foreach (["Acme' OR 1=1 --", '<img src=x onerror=alert(1)>'] as $name) {
            $html = $this->productListFilterPage(['branch_id' => '1', 'manufacturer' => $name]);
            $this->assertSame([], $this->productListFilterIds($html));
            $dom = $this->productListFilterDom($html);
            $this->assertSame($name, $dom->evaluate('string(//select[@name="manufacturer"]/option[@selected]/@value)'));
            $this->assertSame(0, $dom->query('//select[@name="manufacturer"]//img')->length);
        }
    }

    public function testProductListClearFiltersRetainsBranchContextOnly(): void
    {
        $this->productListFilterFixture();
        $html = $this->productListFilterPage(['branch_id' => '1', 'manufacturer' => 'Acme', 'stock_filter' => 'low', 'medicine_type' => 'branded', 'category_id' => '1', 'keyword' => 'FILTER', 'status' => 'active']);
        $dom = $this->productListFilterDom($html);
        $url = $dom->evaluate('string(//form[@aria-label="Product filters"]//a[normalize-space(.)="Clear filters"]/@href)');
        $this->assertSame('branch_id=1', parse_url($url, PHP_URL_QUERY));
        $this->assertSame(2, $dom->query('//table[@aria-label="Products"]/thead/tr/th[@data-dt-filter="off"]')->length);
        $this->assertSame('products-manufacturer', $dom->evaluate('string(//table[@aria-label="Products"]/thead/tr/th[5]/@data-dt-server-filter)'));
        $this->assertSame('products-stock-status', $dom->evaluate('string(//table[@aria-label="Products"]/thead/tr/th[11]/@data-dt-server-filter)'));
        $this->assertSame(2, $dom->query('//select[@form="products-filter-form" and (@name="manufacturer" or @name="stock_filter")]')->length);
        $this->assertSame(1, $dom->query('//table[@aria-label="Products" and @data-dt-empty]')->length);
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListFilterIds($this->productListFilterPage(['branch_id' => '1'])));
    }

    public function testProductListAdminManufacturerFilterCanSpanBranches(): void
    {
        $this->productListFilterFixture();
        $html = $this->productListFilterPage([]);
        $this->assertSame([1, 2, 3, 4, 5, 6, 9], $this->productListFilterIds($html));
        $this->assertSame(1, $this->productListFilterDom($html)->query('//select[@name="manufacturer"]/option[@value="Other Branch Maker"]')->length);
        $filtered = $this->productListFilterPage(['manufacturer' => 'Other Branch Maker']);
        $this->assertSame([6], $this->productListFilterIds($filtered));
        $this->assertSame(0, $this->productListFilterDom($filtered)->query('//form[@aria-label="Product filters"]/input[@name="branch_id"]')->length);
    }

    public function testProductListAjaxFiltersReturnOnlyRowsAndRetainBranchPermissions(): void
    {
        $this->productListFilterFixture();
        $before = $this->productListFilterSnapshot();
        $data = $this->productListFilterAjax(['branch_id' => '1', 'manufacturer' => 'Acme', 'stock_filter' => 'low', 'medicine_type' => 'branded']);
        $this->assertTrue($data['ok']);
        $this->assertFalse($data['truncated']);
        $this->assertSame([2, 9], $this->productListAjaxIds($data));
        $this->assertStringNotContainsString('<script', $data['rows_html']);
        $this->assertStringNotContainsString('<html', $data['rows_html']);
        $this->assertContains('Unilab', $data['manufacturers']);
        session()->set(['role' => 'cashier', 'branch_id' => 1]);
        $data = $this->productListFilterAjax(['branch_id' => '2']);
        $this->assertSame('1', $data['filters']['branch_id']);
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListAjaxIds($data));
        $this->assertNotContains('Other Branch Maker', $data['manufacturers']);
        $this->assertSame($before, $this->productListFilterSnapshot());
    }

    public function testProductListAjaxEmptyResultsCanBeClearedAndNamesRemainEscaped(): void
    {
        $this->productListFilterFixture();
        $data = $this->productListFilterAjax(['branch_id' => '1', 'manufacturer' => 'Unilab', 'stock_filter' => 'out']);
        $this->assertSame([], $this->productListAjaxIds($data));
        $this->assertSame('out', $data['filters']['stock_filter']);
        $this->assertContains('Acme', $data['manufacturers']);
        $data = $this->productListFilterAjax(['branch_id' => '1']);
        $this->assertSame([1, 2, 3, 4, 5, 9], $this->productListAjaxIds($data));
        $data = $this->productListFilterAjax(['branch_id' => '1', 'manufacturer' => "O'Connor & Sons / Pharma", 'stock_filter' => 'in']);
        $this->assertSame([5], $this->productListAjaxIds($data));
        $this->assertStringContainsString('O&#039;Connor &amp; Sons / Pharma', $data['rows_html']);
    }

    public function testProductListAjaxReadsKeepUnspentStockTokensValid(): void
    {
        $this->productListFilterFixture();
        session()->set(['role' => 'cashier', 'branch_id' => 1]);
        $this->productListFilterPage(['branch_id' => '1']);
        $token = session('stock_adjustment_2_1');
        $this->assertIsString($token);
        $data = $this->productListFilterAjax(['manufacturer' => 'Acme']);
        $this->assertSame($token, session('stock_adjustment_2_1'));
        $this->assertStringContainsString($token, $data['rows_html']);
        $this->productListFilterAjax(['manufacturer' => 'Unilab']);
        $this->assertSame($token, session('stock_adjustment_2_1'));
        session()->remove('stock_adjustment_2_1');
        $this->productListFilterAjax(['manufacturer' => 'Acme']);
        $this->assertNotSame($token, session('stock_adjustment_2_1'));
    }
}
