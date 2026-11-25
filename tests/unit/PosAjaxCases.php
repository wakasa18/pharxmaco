<?php

/** Cart-only requests execute real controllers against the owning SQLite fixture. */
trait PosAjaxCases
{
    private function posAjax(string $method, array $post = [], array $arguments = []): array
    {
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, $post);
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $controller->$method(...$arguments);
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        return [$response->getStatusCode(), json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)];
    }

    private function posAjaxSetup(): \App\Libraries\CashierShift
    {
        helper(['url', 'form']);
        $this->retainedModels();
        session()->remove(['cart', 'held_sales', 'checkout_token', 'error', 'success', 'warning']);
        $this->db->table('products')->where('id', 1)->update(['senior_pwd_eligible' => 1, 'tax_treatment' => 'taxable']);
        return $this->cashierSetup();
    }

    public function testPosAjaxCartActionsReturnOnlyUpdatedFragmentsAndKeepStockUnchanged(): void
    {
        $this->posAjaxSetup();
        [$status, $data] = $this->posAjax('addToCart', ['product_id' => '1', 'quantity' => '2']);
        $this->assertSame(200, $status);
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['cart_count']);
        $this->assertSame(2, session('cart')[1]['quantity']);
        $this->assertStringContainsString('cart-row', $data['cart_html']);
        $this->assertStringContainsString('data-tax-treatment="taxable"', $data['benefit_html']);
        $this->assertStringContainsString('No Discount', $data['discount_options_html']);
        $this->assertStringNotContainsString('<script', $data['cart_html']);
        $this->assertNotEmpty($data['csrf_name']);
        $this->assertNotEmpty($data['csrf_hash']);
        $this->assertSame(session('checkout_token'), $data['checkout_token']);
        $token = $data['checkout_token'];
        [$status, $data] = $this->posAjax('updateCart', ['product_id' => '1', 'quantity' => '3']);
        $this->assertSame(200, $status);
        $this->assertSame(3, session('cart')[1]['quantity']);
        $this->assertSame($token, $data['checkout_token']);
        [$status, $data] = $this->posAjax('removeCartItem', ['product_id' => '1']);
        $this->assertSame(200, $status);
        $this->assertSame(0, $data['cart_count']);
        $this->assertStringContainsString('Cart is empty', $data['cart_html']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testPosAjaxInvalidActionKeepsCartAndReturnsVisibleErrorAndFreshTokens(): void
    {
        $this->posAjaxSetup();
        $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 2]);
        $cart = session('cart');
        $token = session('checkout_token');
        [$status, $data] = $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 10]);
        $this->assertSame(422, $status);
        $this->assertFalse($data['success']);
        $this->assertSame('Quantity exceeds stock.', $data['message']);
        $this->assertSame($cart, session('cart'));
        $this->assertSame($token, $data['checkout_token']);
        $this->assertEmpty(session()->getFlashdata('error'));
        $this->assertNotEmpty($data['csrf_hash']);
        $this->db->table('branch_products')->where('id', 1)->update(['expiration_date' => '2020-01-01']);
        [$status, $data] = $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 1]);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('expired', $data['message']);
        $this->assertSame($cart, session('cart'));
    }

    public function testPosAjaxHoldResumeClearAndDiscardRefreshListsAndSeparateCustomers(): void
    {
        $service = $this->posAjaxSetup();
        $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 2]);
        $originalToken = session('checkout_token');
        [$status, $held] = $this->posAjax('holdSale', ['label' => '<script>Customer</script>']);
        $this->assertSame(200, $status);
        $this->assertTrue($held['new_customer']);
        $this->assertSame(0, $held['cart_count']);
        $this->assertSame(1, $held['held_count']);
        $this->assertNotSame($originalToken, $held['checkout_token']);
        $this->assertStringNotContainsString('<script>', $held['held_html']);
        $id = (int) array_values($service->held(1, 1))[0]['id'];
        $this->db->table('branch_products')->where('id', 1)->update(['price' => 12]);
        [$status, $resumed] = $this->posAjax('resumeHeldSale', [], [$id]);
        $this->assertSame(200, $status);
        $this->assertTrue($resumed['new_customer']);
        $this->assertSame(1, $resumed['cart_count']);
        $this->assertSame(0, $resumed['held_count']);
        $this->assertSame(12.0, session('cart')[1]['price']);
        $this->assertNotSame($held['checkout_token'], $resumed['checkout_token']);
        $id = $service->hold(1, 1, session('cart'), 'Another held sale');
        [$status, $discarded] = $this->posAjax('discardHeldSale', [], [$id]);
        $this->assertSame(200, $status);
        $this->assertSame(0, $discarded['held_count']);
        $this->assertFalse($discarded['new_customer']);
        $this->assertSame($resumed['checkout_token'], $discarded['checkout_token']);
        [$status, $cleared] = $this->posAjax('clearCart');
        $this->assertSame(200, $status);
        $this->assertTrue($cleared['new_customer']);
        $this->assertSame(0, $cleared['cart_count']);
        $this->assertNotSame($resumed['checkout_token'], $cleared['checkout_token']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testPosAjaxResumeCannotReplaceAnActiveCartOrTakeAnotherUsersHeldSale(): void
    {
        $service = $this->posAjaxSetup();
        $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 1]);
        $cart = session('cart');
        $id = $service->hold(1, 1, $cart, 'Waiting');
        [$status, $data] = $this->posAjax('resumeHeldSale', [], [$id]);
        $this->assertSame(422, $status);
        $this->assertFalse($data['success']);
        $this->assertSame($cart, session('cart'));
        $this->assertCount(1, $service->held(1, 1));
        $id2 = $service->hold(2, 1, $cart, 'Other cashier');
        [$status, $data] = $this->posAjax('discardHeldSale', [], [$id2]);
        $this->assertSame(422, $status);
        $this->assertFalse($data['success']);
        $this->assertCount(1, $service->held(2, 1));
    }

    public function testPosAjaxRefreshReturnsCurrentPromotionsAndDoesNotResubmitAnAction(): void
    {
        $this->posAjaxSetup();
        $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 2]);
        $this->db->table('discounts')->insert(['id' => 10, 'discount_name' => 'Product promo', 'applies_to' => 'product',
            'product_id' => 1, 'discount_type' => 'percentage', 'discount_value' => 10, 'status' => 'active']);
        $cart = session('cart');
        [$status, $data] = $this->posAjax('index');
        $this->assertSame(200, $status);
        $this->assertSame($cart, session('cart'));
        $this->assertSame(10, $data['promotions'][0]['id']);
        $this->assertStringNotContainsString('Product promo', $data['discount_options_html']);
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $this->assertSame(0, $this->db->table('cashier_held_sales')->countAllResults());
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testPosAjaxRequiresShiftBeforeChangingCartAndNativeFormsStillRedirect(): void
    {
        $service = $this->posAjaxSetup();
        $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 1]);
        $response = $this->controller(\App\Controllers\Cashier\SalesController::class, [])->clearCart();
        $this->assertSame(site_url('cashier/sales'), $response->getHeaderLine('Location'));
        $this->assertEmpty(session('cart'));
        $shift = $service->activeFor(1);
        $summary = $service->summary($shift);
        $service->close((int) $shift['id'], 1, '100', '100', '', $summary['revision'], null);
        [$status, $data] = $this->posAjax('addToCart', ['product_id' => 1, 'quantity' => 2]);
        $this->assertSame(409, $status);
        $this->assertFalse($data['success']);
        $this->assertSame(site_url('cashier/start-shift'), $data['redirect_url']);
        $this->assertEmpty(session('cart'));
    }
}
