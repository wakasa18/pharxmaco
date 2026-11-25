<?php

use PHPUnit\Framework\TestCase;

require_once ROOTPATH.'tests/unit/BusinessLogicTest.php';

/** Post-fix regressions; the original observations remain in probe-results.json. */
final class LatestAuditProbeTest extends TestCase
{
    private BusinessLogicTest $fixture;
    private static array $observations = [];
    protected function setUp(): void
    {
        $this->fixture = new BusinessLogicTest('testCheckoutRollsBackSaleAndStockWhenAuditFails');
        $this->invoke('setUp');
    }
    protected function tearDown(): void
    {
        $this->invoke('tearDown');
        file_put_contents(__DIR__.'/fixed-probe-results.json',json_encode(self::$observations,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod(BusinessLogicTest::class,$method))->invoke($this->fixture,...$args);
    }
    private function db(): object
    {
        return (new ReflectionProperty(BusinessLogicTest::class,'db'))->getValue($this->fixture);
    }

    public function testOldCompletedCheckoutRetryPreservesANewerCustomersCart(): void
    {
        $db=$this->db();$db->query('ALTER TABLE sales ADD COLUMN checkout_token_hash TEXT');
        $db->query('CREATE UNIQUE INDEX audit_checkout_token ON sales(checkout_token_hash)');
        $post=$this->invoke('checkoutPost',['amount_paid'=>'10']);
        $this->invoke('controller',\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->assertSame(2,$db->table('sales')->countAllResults());
        $this->invoke('seedExchangeReplacement',2,20);
        $newCart=[2=>['product_id'=>2,'quantity'=>1,'price'=>20,'subtotal'=>20]];
        session()->set(['cart'=>$newCart,'checkout_token'=>str_repeat('b',32)]);
        $response=$this->invoke('controller',\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        self::$observations['old_checkout_retry_clears_new_cart']=['old_sale_id'=>2,'new_cart_before'=>$newCart,
            'new_cart_after'=>session('cart'),'sale_count'=>$db->table('sales')->countAllResults(),'redirect'=>$response->getHeaderLine('Location')];
        $this->assertSame($newCart,session('cart'));
        $this->assertSame(str_repeat('b',32),session('checkout_token'));
        $this->assertSame(2,$db->table('sales')->countAllResults(),'No duplicate sale is created in this reproduction.');
    }

    public function testEqualTotalQuantityCorrectionReachesCashierProductActivity(): void
    {
        $db=$this->db();$this->invoke('seedExchangeReplacement',2,10);
        $db->table('sales')->where('id',1)->update(['total_amount'=>60,'final_total'=>60,'change_amount'=>40]);
        $db->table('sale_items')->insert(['id'=>2,'sale_id'=>1,'product_id'=>2,'product_name_snapshot'=>'Second item','quantity'=>1,
            'price'=>10,'cost_price_at_sale'=>4,'subtotal'=>10,'discount_applied'=>0,'profit'=>6]);
        $service=$this->invoke('cashierSetup');$shift=$service->activeFor(1);
        $db->transBegin();$service->recordSale($service->requireActive(1,1),1);$db->transCommit();
        $post=$this->invoke('correctionPost',['items'=>[1=>4,2=>2]]);
        $this->invoke('controller',\App\Controllers\Admin\SaleCorrection::class,$post)->update(1);
        $items=$db->table('sale_items')->where('sale_id',1)->orderBy('product_id')->get()->getResultArray();
        $summary=$service->summary($shift);
        self::$observations['equal_total_correction_missing_from_day']=['corrected_quantities'=>array_column($items,'quantity','product_id'),
            'reported_products'=>$summary['products'],'cashier_event_count'=>$db->table('cashier_events')->countAllResults(),
            'final_total'=>$db->table('sales')->where('id',1)->get()->getRowArray()['final_total']];
        $this->assertEquals([4,2],array_column($items,'quantity'));
        $this->assertEquals([5,1],array_column($summary['products'],'sold'));
        $this->assertEquals([-1,1],array_column($summary['products'],'quantity_adjustments'));
        $this->assertSame(2,$db->table('cashier_events')->countAllResults());
    }

    public function testTwoIndividuallyTiedPromosReceiveTheBetterCombinedCentavoTotal(): void
    {
        foreach(['non_vat','vat']as$status){
            $config=new \Config\ReceiptInvoice();$config->taxStatus=$status;$config->vatRate=12.0;
            $price=$status==='vat'?112.02:100.02;$value=$status==='vat'?32.01:20.01;
            $items=[];$promos=[];
            foreach([1,2]as$id){
                $items[$id]=['product_id'=>$id,'subtotal'=>$price,'senior_pwd_eligible'=>1,'tax_treatment'=>'taxable'];
                $promos[]=['id'=>$id+10,'product_id'=>$id,'discount_name'=>'Centavo offer '.$id,'applies_to'=>'product',
                    'status'=>'active','discount_type'=>'fixed','discount_value'=>$value];
            }
            $benefit=\App\Libraries\StatutoryDiscount::quote('pwd',$items,['beneficiary_name'=>'Audit beneficiary',
                'beneficiary_id'=>'AUDIT-ID','benefit_verified'=>'1','benefit_products'=>[1=>'1',2=>'1']],$config);
            $combined=\App\Libraries\BenefitProductPromotion::combine('pwd',$benefit,$items,$promos,'2026-10-16');
            $ordinary=\App\Libraries\ProductPromotion::all($promos,$items,'2026-10-16');
            $gross=\App\Libraries\DiscountPolicy::cents($price)*2;
            $chosen=$gross-\App\Libraries\DiscountPolicy::cents($combined['discount'])-\App\Libraries\DiscountPolicy::cents($combined['vat_exemption']);
            $alternative=$gross-\App\Libraries\DiscountPolicy::cents(\App\Libraries\ProductPromotion::total($ordinary));
            self::$observations['rounding_combination_misses_better_total'][$status]=['chosen_discount'=>$combined['discount'],
                'vat_relief'=>$combined['vat_exemption'],'both_promos_discount'=>\App\Libraries\ProductPromotion::total($ordinary),
                'chosen_payable'=>$chosen/100,'both_promos_payable'=>$alternative/100,'applied_promos'=>$combined['promotions']];
            $this->assertSame($status==='vat'?6402:4002,\App\Libraries\DiscountPolicy::cents($combined['discount']));
            $this->assertSame(16002,$chosen);$this->assertSame(16002,$alternative);
        }
    }
}
