<?php

/** Branded visibility follows sellable branch stock, with ingredient-aware search. */
trait PosBrandedCases
{
    private function posBrandedFixture(): void
    {
        helper(['url', 'form']);
        $this->retainedModels();
        session()->remove(['cart', 'held_sales', 'error', 'success', 'warning']);
        $this->db->table('products')->where('id', 1)->update(['product_name' => 'Paracetamol 500 mg tablet', 'generic_name' => 'Paracetamol', 'medicine_type' => 'generic']);
        foreach ([2 => 'Bioflu tablet', 3 => 'Expired branded medicine', 4 => 'Sold out branded medicine', 5 => 'Inactive branded medicine', 6 => 'Other branch branded medicine', 7 => 'Trashed branded medicine', 8 => 'Hidden branded medicine'] as $id => $name) {
            $this->seedExchangeReplacement($id, 12);
            $this->db->table('products')->where('id', $id)->update(['product_name' => $name, 'medicine_type' => 'branded', 'generic_name' => 'Phenylephrine + Paracetamol', 'unit' => 'tablet']);
        }
        $this->db->table('branch_products')->where('id', 3)->update(['expiration_date' => '2000-01-01']);
        $this->db->table('branch_products')->where('id', 4)->update(['stock' => 0]);
        $this->db->table('products')->where('id', 5)->update(['status' => 'inactive']);
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Second Branch', 'status' => 'active']);
        $this->db->table('branch_products')->where('id', 6)->update(['branch_id' => 2]);
        $this->db->table('branch_products')->where('id', 7)->update(['is_deleted' => 1, 'deleted_at' => '2026-10-01 12:00:00']);
        $this->db->table('products')->where('id', 8)->update(['is_permanently_deleted' => 1]);
    }

    private function posBrandedCatalog(string $keyword = '', bool $legacySearch = false): string
    {
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, []);
        service('request')->setGlobal('get', ['q' => $keyword]);
        if ($legacySearch) service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $html = $legacySearch ? $controller->searchProducts() : $controller->index();
        $this->assertIsString($html);
        return $html;
    }

    private function posBrandedIds(string $html, bool $legacySearch = false): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $query = new DOMXPath($document);
        $nodes = $query->query($legacySearch
            ? '//tr[contains(concat(" ",normalize-space(@class)," ")," product-result-row ")]'
            : '//div[contains(concat(" ",normalize-space(@class)," ")," prod-card ")]');
        $ids = [];
        foreach ($nodes as $node) $ids[] = (int) $node->getAttribute($legacySearch ? 'data-product-id' : 'data-pid');
        sort($ids);
        return $ids;
    }

    public function testPosBrandedInitialCatalogIncludesSaleableBrandedAndGenericProducts(): void
    {
        $this->posBrandedFixture();
        $before = $this->db->table('branch_products')->orderBy('id')->get()->getResultArray();
        $html = $this->posBrandedCatalog();
        $this->assertSame([1, 2], $this->posBrandedIds($html));
        $this->assertStringContainsString('data-medicine-type="branded"', $html);
        $this->assertStringContainsString('Bioflu tablet', $html);
        $this->assertSame($before, $this->db->table('branch_products')->orderBy('id')->get()->getResultArray());
    }

    public function testPosBrandedGenericIngredientSearchFindsBrandInInitialAndAjaxResults(): void
    {
        $this->posBrandedFixture();
        foreach ([false, true] as $legacy) {
            $html = $this->posBrandedCatalog('Paracetamol', $legacy);
            $this->assertSame([1, 2], $this->posBrandedIds($html, $legacy));
            $this->assertStringContainsString('Bioflu tablet', $html);
            $this->assertStringContainsString('data-medicine-type="branded"', $html);
        }
    }

    public function testPosBrandedSearchKeepsNameAndSkuMatchingWithoutLeakingUnavailableStock(): void
    {
        $this->posBrandedFixture();
        foreach ([true] as $legacy) {
            $this->assertSame([2], $this->posBrandedIds($this->posBrandedCatalog('Bioflu', $legacy), $legacy));
            $this->assertSame([2], $this->posBrandedIds($this->posBrandedCatalog('REPLACE-2', $legacy), $legacy));
            $this->assertSame([], $this->posBrandedIds($this->posBrandedCatalog('Expired branded', $legacy), $legacy));
            $this->assertSame([], $this->posBrandedIds($this->posBrandedCatalog('Other branch branded', $legacy), $legacy));
        }
    }

    public function testPosBrandedUrlSearchKeepsFullSellableCatalogForClearAndReset(): void
    {
        $this->posBrandedFixture();
        $html = $this->posBrandedCatalog('Bioflu');
        $this->assertSame([1, 2], $this->posBrandedIds($html));
        $this->assertStringContainsString('id="productSearch" value="Bioflu"', $html);
        $this->assertStringContainsString('data-medicine-type="generic"', $html);
        $this->assertStringContainsString('data-medicine-type="branded"', $html);
    }

    public function testPosBrandedTodayExpiryIsUnavailableAndAnotherBranchUsesItsOwnStock(): void
    {
        $this->posBrandedFixture();
        $this->db->table('branch_products')->where('id', 2)->update(['expiration_date' => date('Y-m-d')]);
        $this->assertSame([1], $this->posBrandedIds($this->posBrandedCatalog()));
        session()->set(['role' => 'cashier', 'branch_id' => 2]);
        foreach ([false, true] as $legacy) {
            $html = $this->posBrandedCatalog('Paracetamol', $legacy);
            $this->assertSame([6], $this->posBrandedIds($html, $legacy));
        }
    }
}
