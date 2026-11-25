<?php

/** Manual payment-reference validation against synthetic records only. */
trait PaymentReferenceCases
{
    public function testPaymentReferenceCorrectionRejectsStructuredPaymentFieldsWithoutWrites(): void
    {
        $before = $this->paymentReferenceSnapshot();
        foreach (['payment_method', 'reference_no'] as $field) {
            $post = $this->correctionPost(['items' => [1 => 5]]);
            $post[$field] = ['unexpected array'];
            $html = $this->controller(\App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
            $this->assertIsString($html);
            $this->assertStringContainsString('text reference number', $html);
            $this->assertSame($before, $this->paymentReferenceSnapshot());
        }
    }
    private function paymentReferenceRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('An invalid payment reference was accepted.');
        } catch (\InvalidArgumentException $error) {
            $this->assertNotSame('', $error->getMessage());
        }
    }

    private function paymentReferenceSnapshot(): array
    {
        $snapshot = [];
        foreach (['products', 'branch_products', 'sales', 'sale_items', 'refund_items', 'stock_logs', 'activity_logs'] as $table) {
            $snapshot[$table] = $this->db->table($table)->orderBy('id')->get()->getResultArray();
        }
        return $snapshot;
    }

    public function testPaymentReferenceGcashUsesThirteenAsciiDigitsAndPreservesLeadingZeros(): void
    {
        $this->assertSame('0012345678901', \App\Libraries\PaymentReference::assertAllowed('gcash', ' 0012345678901 '));
        $this->assertSame('0000000000001', \App\Libraries\PaymentReference::assertAllowed('gcash', '0000000000001'));
        foreach (['', '   ', '1234567890', '123456789012', '12345678901234', 'GCASH12345678', '001 2345678901',
            '001-2345678901', '00123456789.1', '１２３４５６７８９０１２３', '١٢٣٤٥٦٧٨٩٠١٢٣', "001234\n5678901", '<12345678901>'] as $reference) {
            $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed('gcash', $reference));
        }
    }

    public function testPaymentReferenceCardAcceptsProviderIdentifiersWithoutAssumingOneLength(): void
    {
        foreach (['05754C', '00001234', '123456789012', 'NC6HT9CRT65ZGN82',
            '0bea058b-ae8e-4bfc-90d0-7fa0ff6fa5a6', 'MAYA / 00001234._-ab', 'A', str_repeat('Z', 100)] as $reference) {
            $this->assertSame($reference, \App\Libraries\PaymentReference::assertAllowed('card', ' ' . $reference . ' '));
        }
    }

    public function testPaymentReferenceCardRejectsMarkupControlsAndSymbolOnlyValues(): void
    {
        foreach (['', '   ', '-._/ ', 'é1234', 'AB💳123', '<script>', 'REF@123', 'REF:123', "AB\t123", "AB\n123", str_repeat('A', 101)] as $reference) {
            $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed('card', $reference));
        }
    }

    public function testPaymentReferenceOptionalDoesNotBypassValidationOfProvidedOrStructuredValues(): void
    {
        foreach (['gcash', 'card'] as $method) {
            $this->assertSame('', \App\Libraries\PaymentReference::assertAllowed($method, '', false));
            $this->assertSame('', \App\Libraries\PaymentReference::assertAllowed($method, null, false));
            foreach ([[], ['0012345678901'], new \stdClass()] as $reference) {
                $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed($method, $reference));
                $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed($method, $reference, false));
            }
        }
        $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed('gcash', 'SHORT', false));
        $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed('card', '<ref>', false));
        foreach (['', 'bank', 'Card', 'GCash'] as $method) {
            $this->paymentReferenceRejected(fn () => \App\Libraries\PaymentReference::assertAllowed($method, '0012345678901'));
        }
    }

    public function testPaymentReferenceCashHasNoStoredDigitalReference(): void
    {
        foreach ([null, '', 'CASH-NOTE', ['not a digital reference']] as $reference) {
            $this->assertSame('', \App\Libraries\PaymentReference::assertAllowed('cash', $reference));
        }
    }

    public function testPaymentReferenceCheckoutRejectsMalformedReferencesWithoutWritingSaleOrStock(): void
    {
        $before = $this->paymentReferenceSnapshot();
        foreach ([['gcash', '123456789012'], ['gcash', '12345678901234'], ['gcash', 'GCASH-1234567'],
            ['card', '<script>'], ['card', '-._/'], ['card', str_repeat('A', 101)]] as [$method, $reference]) {
            $post = $this->checkoutPost(['payment_method' => $method, 'amount_paid' => '10.00', 'reference_no' => $reference]);
            $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $this->assertSame($before, $this->paymentReferenceSnapshot());
            $this->assertNotEmpty(session()->getFlashdata('error'));
            $this->assertNotEmpty(session('cart'));
        }
    }

    public function testPaymentReferenceCheckoutSavesTrimmedGcashReferenceWithExactPayment(): void
    {
        $post = $this->checkoutPost(['payment_method' => 'gcash', 'amount_paid' => '10.00', 'reference_no' => ' 0012345678901 ']);
        $response = $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $this->assertStringContainsString('/completed/', $response->getHeaderLine('Location'));
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertNotNull($sale);
        $this->assertSame('0012345678901', $sale['reference_no']);
        $this->assertSame('gcash', $sale['payment_method']);
        $this->assertSame(10.0, (float) $sale['amount_paid']);
        $this->assertSame(0.0, (float) $sale['change_amount']);
        $this->assertSame(9, (int) $this->row('branch_products')['stock']);
    }

    public function testPaymentReferenceCheckoutPreservesEightCharacterCardApprovalAndCase(): void
    {
        $post = $this->checkoutPost(['payment_method' => 'card', 'amount_paid' => '10.00', 'reference_no' => ' 0000aB34 ']);
        $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertNotNull($sale);
        $this->assertSame('0000aB34', $sale['reference_no']);
        $this->assertSame('card', $sale['payment_method']);
        $this->assertSame(0.0, (float) $sale['change_amount']);
    }

    public function testPaymentReferenceExchangeValidatesBothChargeAndPayoutReferencesBeforeWrites(): void
    {
        $service = new \App\Libraries\ProductExchange($this->db);
        $base = $this->exchangeInput(['settlement_method' => 'gcash', 'reference' => '0012345678901']);
        $before = $this->paymentReferenceSnapshot();
        $quote = $service->quote(1, $base, 1, 'admin');
        $this->assertSame(1000, $quote['difference']);
        $this->assertSame('0012345678901', $quote['reference']);
        $bad = array_replace($base, ['reference' => 'NOT-A-GCASH-REFERENCE']);
        $this->paymentReferenceRejected(fn () => $service->complete(1, $bad, 1, 'admin', 1, str_repeat('a', 48), $quote['revision']));
        $this->assertSame($before, $this->paymentReferenceSnapshot());
        $payout = array_replace($base, ['returns' => [1 => 5], 'replacements' => [100 => 1], 'reference' => '0001234567890']);
        $this->assertSame(-4000, $service->quote(1, $payout, 1, 'admin')['difference']);
        $this->paymentReferenceRejected(fn () => $service->quote(1, array_replace($payout, ['reference' => 'SHORT']), 1, 'admin'));
        $this->assertSame($before, $this->paymentReferenceSnapshot());
    }

    public function testPaymentReferenceEvenExchangeAllowsBlankButValidatesAnySuppliedReference(): void
    {
        $service = new \App\Libraries\ProductExchange($this->db);
        $base = $this->exchangeInput(['replacements' => [100 => 2], 'settlement_method' => 'gcash', 'reference' => '']);
        $quote = $service->quote(1, $base, 1, 'admin');
        $this->assertSame(0, $quote['difference']);
        $this->assertSame('', $quote['reference']);
        $this->assertSame('0012345678901', $service->quote(1, array_replace($base, ['reference' => '0012345678901']), 1, 'admin')['reference']);
        foreach ([['gcash', 'SHORT'], ['card', '<invalid>'], ['gcash', ['0012345678901']]] as [$method, $reference]) {
            $this->paymentReferenceRejected(fn () => $service->quote(1, array_replace($base, ['settlement_method' => $method, 'reference' => $reference]), 1, 'admin'));
        }
    }

    public function testPaymentReferenceCardExchangeAcceptsProviderReferenceWithoutSixCharacterRestriction(): void
    {
        $service = new \App\Libraries\ProductExchange($this->db);
        $input = $this->exchangeInput(['settlement_method' => 'card', 'reference' => ' 0bea058b-ae8e-4bfc-90d0-7fa0ff6fa5a6 ']);
        $quote = $service->quote(1, $input, 1, 'admin');
        $this->assertSame(1000, $quote['difference']);
        $this->assertSame('0bea058b-ae8e-4bfc-90d0-7fa0ff6fa5a6', $quote['reference']);
    }

    public function testPaymentReferenceCorrectionRejectsNewInvalidNoncashReferenceWithoutWrites(): void
    {
        $before = $this->paymentReferenceSnapshot();
        foreach ([['gcash', 'SHORT'], ['gcash', ''], ['card', '<reference>'], ['card', '-._/']] as [$method, $reference]) {
            $post = $this->correctionPost(['items' => [1 => 5], 'payment_method' => $method, 'reference_no' => $reference]);
            $this->controller(\App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
            $this->assertSame($before, $this->paymentReferenceSnapshot());
        }
    }

    public function testPaymentReferenceCorrectionAcceptsChangedGcashReferenceWithoutStockChanges(): void
    {
        $post = $this->correctionPost(['items' => [1 => 5], 'payment_method' => 'gcash', 'reference_no' => ' 0012345678901 ']);
        $this->controller(\App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
        $this->assertSame('gcash', $this->row('sales')['payment_method']);
        $this->assertSame('0012345678901', $this->row('sales')['reference_no']);
        $this->assertSame(5, (int) $this->row('sale_items')['quantity']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testPaymentReferenceCorrectionKeepsUnchangedLegacyReferenceForMetadataOnlyEdit(): void
    {
        $this->db->table('sales')->where('id', 1)->update(['payment_method' => 'gcash', 'reference_no' => 'LEGACY-GCASH']);
        $item = $this->row('sale_items');
        $post = $this->correctionPost(['items' => [1 => 5], 'payment_method' => 'gcash', 'reference_no' => 'LEGACY-GCASH', 'notes' => 'Reviewed historical payment note']);
        $this->controller(\App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
        $this->assertSame('Reviewed historical payment note', $this->row('sales')['notes']);
        $this->assertSame('LEGACY-GCASH', $this->row('sales')['reference_no']);
        $this->assertSame($item, $this->row('sale_items'));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
    }

    public function testPaymentReferenceCorrectionFinancialDifferenceCannotReuseInvalidLegacyReference(): void
    {
        $this->db->table('sales')->where('id', 1)->update(['payment_method' => 'gcash', 'reference_no' => 'LEGACY-GCASH']);
        $before = $this->paymentReferenceSnapshot();
        $post = $this->correctionPost(['items' => [1 => 6], 'payment_method' => 'gcash', 'reference_no' => 'LEGACY-GCASH']);
        $this->controller(\App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
        $this->assertSame($before, $this->paymentReferenceSnapshot());
    }
}
