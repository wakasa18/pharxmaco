<?php

trait SubmitReviewCases
{
    public function testSubmitReviewProductUsesSavedBranchPricesAndPreservesUnspecifiedTax(): void
    {
        helper(['url','form']);
        $this->managementFixtures();
        $this->db->table('products')->where('id', 1)->update(['cost_price' => 4, 'price' => 10, 'unit' => 'tablet', 'tax_treatment' => null]);
        $this->db->table('branch_products')->where('id', 1)->update(['cost_price' => 8, 'price' => 12]);
        service('request')->setGlobal('get', ['branch_id' => 1]);
        $html = $this->controller(\App\Controllers\Products::class, [])->edit(1);
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame('8', $xpath->query('//input[@name="cost_price"]')->item(0)->getAttribute('value'));
        $this->assertSame('12', $xpath->query('//input[@name="price"]')->item(0)->getAttribute('value'));
        $this->assertSame('', $xpath->query('//select[@name="tax_treatment"]/option[@selected]')->item(0)->getAttribute('value'));
        $baseline = json_decode($xpath->query('//script[@id="product-edit-form-review-original"]')->item(0)->textContent, true);
        $this->assertSame(12, (int) $baseline['price']);
        $this->assertSame(8, (int) $baseline['cost_price']);
        $this->assertSame(10, (int) $baseline['stock']);
        $this->assertSame(10, (int) $this->row('products')['price']);
        $this->assertSame('edit', $xpath->query('//form[@id="product-edit-form"]')->item(0)->getAttribute('data-review-mode'));
    }

    public function testSubmitReviewProductRedisplayComparesAgainstSavedValuesAndEscapesSnapshot(): void
    {
        helper(['url','form']);
        $this->managementFixtures();
        $this->db->table('products')->where('id', 1)->update(['product_name' => 'Safe </script><script>value</script>', 'unit' => 'tablet']);
        session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => ['price' => '25.00', 'stock' => '11']]);
        $html = $this->controller(\App\Controllers\Products::class, [])->edit(1);
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame('25.00', $xpath->query('//input[@name="price"]')->item(0)->getAttribute('value'));
        $raw = $xpath->query('//script[@id="product-edit-form-review-original"]')->item(0)->textContent;
        $this->assertStringNotContainsString('</script>', $raw);
        $baseline = json_decode($raw, true);
        $this->assertSame(10, (int) $baseline['price']);
        $this->assertSame(10, (int) $baseline['stock']);
        $this->assertSame('Safe </script><script>value</script>', $baseline['product_name']);
    }

    public function testSubmitReviewDiscountCreateAndRejectedEditKeepCorrectOriginalSnapshot(): void
    {
        helper(['url','form']);
        $fixtures = $this->managementFixtures();
        $post = $fixtures['discount'][3];
        $this->db->table('discounts')->where('id', 1)->update($post);
        $create = $this->controller(\App\Controllers\Admin\Discounts::class, [])->create();
        $this->assertStringContainsString('data-review-mode="create"', $create);
        $this->assertStringContainsString('Review new discount', $create);
        $invalid = array_replace($post, ['discount_name' => 'Submitted but rejected', 'discount_value' => '110']);
        $html = $this->controller(\App\Controllers\Admin\Discounts::class, $invalid)->update(1);
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame('110', $xpath->query('//input[@name="discount_value"]')->item(0)->getAttribute('value'));
        $baseline = json_decode($xpath->query('//script[@id="discountForm-review-original"]')->item(0)->textContent, true);
        $this->assertSame(20, (int) $baseline['discount_value']);
        $this->assertSame($post['discount_name'], $baseline['discount_name']);
        $this->assertSame($post['discount_name'], $this->row('discounts')['discount_name']);
        $this->assertSame('edit', $xpath->query('//form[@id="discountForm"]')->item(0)->getAttribute('data-review-mode'));
    }
}
