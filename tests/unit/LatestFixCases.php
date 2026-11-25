<?php

/** Real checkout/correction controllers; the owning fixture uses in-memory SQLite. */
trait LatestFixCases
{
    public function testCentavoWinningPromoCombinationIsPersistedForBothBenefitsAndTaxStatuses(): void
    {
        $this->seedExchangeReplacement(2,112.02);
        $this->db->table('products')->where('id',2)->update(['tax_treatment'=>'taxable','senior_pwd_eligible'=>1]);
        foreach ([1,2] as $id) $this->db->table('discounts')->insert(['id'=>$id+10,'discount_name'=>'Centavo offer '.$id,
            'discount_type'=>'fixed','discount_value'=>32.01,'applies_to'=>'product','product_id'=>$id,'status'=>'active','minimum_purchase'=>0]);
        $saleId=1;
        foreach (['vat','non_vat'] as $status) foreach (['senior_citizen','pwd'] as $kind) {
            $value=$status==='vat'?32.01:20.01;$price=$status==='vat'?112.02:100.02;
            $post=$this->statutoryCheckoutPost($kind,['items'=>[1=>1,2=>1],'amount_paid'=>'160.02','include_product_promos'=>'1',
                'benefit_products'=>[1=>'1',2=>'1'],'auto_promo_amount'=>number_format($value*2,2,'.',''),
                'auto_promo_snapshot'=>json_encode([['id'=>11,'product_id'=>1,'amount_cents'=>(int)round($value*100)],
                    ['id'=>12,'product_id'=>2,'amount_cents'=>(int)round($value*100)]])]);
            config(\Config\ReceiptInvoice::class)->taxStatus=$status;
            $this->db->table('branch_products')->whereIn('product_id',[1,2])->update(['price'=>$price]);
            $this->db->table('discounts')->whereIn('id',[11,12])->update(['discount_value'=>$value]);
            session()->set('cart',[1=>['product_id'=>1,'quantity'=>1],2=>['product_id'=>2,'quantity'=>1]]);
            $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
            $sale=$this->db->table('sales')->where('id',++$saleId)->get()->getRowArray();
            $this->assertNotNull($sale,(string)session()->getFlashdata('error'));
            $this->assertEquals(160.02,$sale['final_total']);
            $this->assertEquals(0,$sale['change_amount']);
            $this->assertEquals($value*2,$sale['discount_amount']);
            $items=$this->db->table('sale_items')->where('sale_id',$saleId)->get()->getResultArray();
            $this->assertEquals([0,0],array_column($items,'vat_exemption_amount'));
            $this->assertEquals([$value,$value],array_column($items,'discount_applied'));
            $this->assertSame([80.01,80.01],array_map([\App\Libraries\StatutoryDiscount::class,'netItem'],$items));
            $this->assertSame($kind,\App\Libraries\StatutoryDiscount::forSale($sale)['kind']);
        }
        $this->assertSame(5,$this->db->table('sales')->countAllResults());
    }

    public function testOldCheckoutRecoveryPreservesTheNextCustomersCartAndToken(): void
    {
        $this->db->query('ALTER TABLE sales ADD COLUMN checkout_token_hash TEXT');
        $this->db->query('CREATE UNIQUE INDEX recovery_token ON sales(checkout_token_hash)');
        $post=$this->checkoutPost(['amount_paid'=>'10']);
        $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->seedExchangeReplacement(2,20);
        $newCart=[2=>['product_id'=>2,'quantity'=>1,'price'=>20,'subtotal'=>20]];
        $newToken=str_repeat('b',32);
        session()->set(['cart'=>$newCart,'checkout_token'=>$newToken]);
        $response=$this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->assertStringContainsString('/completed/2',$response->getHeaderLine('Location'));
        $this->assertSame($newCart,session('cart'));
        $this->assertSame($newToken,session('checkout_token'));
        $this->assertSame(2,$this->db->table('sales')->countAllResults());
        $this->assertSame(1,$this->db->table('stock_logs')->countAllResults());

        session()->remove('cart');
        $post['items']=[];
        $response=$this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->assertStringContainsString('/completed/2',$response->getHeaderLine('Location'));
        $this->assertSame($newToken,session('checkout_token'));

        session()->set(['cart'=>$newCart,'checkout_token'=>$post['_checkout_token']]);
        $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->assertEmpty(session('cart'));
        $this->assertNotSame($post['_checkout_token'],session('checkout_token'));
    }

    public function testEqualTotalCorrectionRecordsProductMovementsWithoutChangingDrawerCash(): void
    {
        $this->seedExchangeReplacement(2,10);
        $this->db->table('sales')->where('id',1)->update(['total_amount'=>60,'final_total'=>60,'change_amount'=>40]);
        $this->db->table('sale_items')->insert(['id'=>2,'sale_id'=>1,'product_id'=>2,'product_name_snapshot'=>'Second item','quantity'=>1,
            'price'=>10,'cost_price_at_sale'=>4,'subtotal'=>10,'discount_applied'=>0,'profit'=>6,
            'stock_delivery_id'=>md5('test-delivery-2'),'expiration_date_at_sale'=>'2099-01-01']);
        $service=$this->cashierSetup();$shift=$service->activeFor(1);
        $this->db->transBegin();$service->recordSale($service->requireActive(1,1),1);$this->db->transCommit();
        $this->controller(\App\Controllers\Admin\SaleCorrection::class,$this->correctionPost(['items'=>[1=>4,2=>2]]))->update(1);
        $items=$this->db->table('sale_items')->where('sale_id',1)->orderBy('product_id')->get()->getResultArray();
        $summary=$service->summary($shift);
        $this->assertEquals([4,2],array_column($items,'quantity'));
        $this->assertEquals([5,1],array_column($summary['products'],'sold'));
        $this->assertEquals([-1,1],array_column($summary['products'],'quantity_adjustments'));
        $this->assertEquals([40,20],array_column($summary['products'],'amount'));
        $this->assertSame(2,$this->db->table('cashier_events')->countAllResults());
        $this->assertSame(1,$summary['adjustment_count']);
        $this->assertEquals(160,$summary['expected_cash']);
        $this->assertEquals(60,$this->row('sales')['final_total']);

        $this->controller(\App\Controllers\Admin\SaleCorrection::class,$this->correctionPost(['items'=>[1=>4,2=>2],'notes'=>'Metadata only']))->update(1);
        $this->assertSame(2,$this->db->table('cashier_events')->countAllResults(),'Notes alone do not create money or product movements.');
    }
}
