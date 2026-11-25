<?php

use PHPUnit\Framework\TestCase;

require_once ROOTPATH . 'tests/unit/BusinessLogicTest.php';

/** Reproductions only: all controller writes use the existing in-memory SQLite fixture. */
final class AuditProbeTest extends TestCase
{
    private BusinessLogicTest $fixture;
    private static array $observations = [];

    protected function setUp(): void
    {
        $this->fixture = new BusinessLogicTest('testCheckoutRollsBackSaleAndStockWhenAuditFails');
        $this->callFixture('setUp');
    }

    protected function tearDown(): void
    {
        $this->callFixture('tearDown');
        file_put_contents(__DIR__ . '/probe-results.json', json_encode(self::$observations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function callFixture(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod(BusinessLogicTest::class, $method))->invoke($this->fixture, ...$args);
    }

    private function database(): object
    {
        return (new ReflectionProperty(BusinessLogicTest::class, 'db'))->getValue($this->fixture);
    }

    public function testReceiveRequestCanBeAppliedTwice(): void
    {
        $post = ['product_id' => 1, 'branch_id' => 1, 'old_stock' => 10, 'stock' => 5,
            'stock_mode' => 'receive', 'remarks' => 'Audit duplicate delivery'];
        $stocks = [10];
        foreach ([1, 2] as $submission) {
            $this->callFixture('controller', \App\Controllers\Products::class, $post)->updateStock();
            $stocks[] = (int) $this->callFixture('row', 'branch_products')['stock'];
        }
        self::$observations['same_receive_request_twice'] = ['stock' => $stocks,
            'stock_logs' => $this->database()->table('stock_logs')->countAllResults()];
        $this->assertSame([10, 15, 20], $stocks, 'Audit observation changed: inspect receiving protection.');
    }

    public function testGcashCheckoutAcceptsOverpaymentWithoutReference(): void
    {
        $this->probeDigitalPayment('gcash');
    }

    public function testCardCheckoutAcceptsOverpaymentWithoutReference(): void
    {
        $this->probeDigitalPayment('card');
    }

    private function probeDigitalPayment(string $method): void
    {
        $post = $this->callFixture('checkoutPost', ['payment_method' => $method,
            'amount_paid' => '100', 'reference_no' => '']);
        $this->callFixture('controller', \App\Controllers\Cashier\SalesController::class, $post)->checkout();
        $sale = $this->database()->table('sales')->where('id >', 1)->get()->getRowArray();
        self::$observations[$method . '_overpayment_no_reference'] = $sale ? array_intersect_key($sale,
            array_flip(['final_total', 'amount_paid', 'change_amount', 'payment_method', 'reference_no'])) : ['accepted' => false];
        $this->assertNotNull($sale, 'Audit observation changed: checkout rejected this payment.');
        $this->assertEquals(10, $sale['final_total']);
        $this->assertEquals(90, $sale['change_amount']);
        $this->assertEmpty($sale['reference_no']);
    }
}
