<?php
require_once __DIR__ . '/InventoryAuditFixture.php';
final class InventoryExtraAuditTest extends InventoryAuditFixture {
 private function fixture(string $method,...$args) { return (new ReflectionMethod(InventoryAuditFixture::class,$method))->invoke($this,...$args); }
 private function evidence(string $name,array $data):void { file_put_contents(__DIR__.'/'.$name.'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); }
 public function testAuditAliasedRefundItemIdsOverpayAndDoubleRestock():void {
  session()->set('refund_token_1','alias-audit');
  $post=['_refund_token'=>'alias-audit','refund'=>[1=>5,'01'=>5],'reason'=>'Audit alias quantities','refund_method'=>'cash','return_condition'=>[1=>'resellable']];
  $this->fixture('controller',\App\Controllers\Cashier\SalesController::class,$post)->refundPartial(1);
  $rows=$this->db->table('refund_items')->get()->getResultArray();
  $qty=(int)array_sum(array_column($rows,'quantity_refunded')); $paid=(float)array_sum(array_column($rows,'refund_subtotal'));$stock=(int)$this->fixture('row','branch_products')['stock'];
  $this->assertCount(2,$rows);$this->assertSame(10,$qty);$this->assertSame(100.0,$paid);$this->assertSame(20,$stock);
  $this->evidence('refund-alias',['sold_quantity'=>5,'sale_net'=>50,'request_keys'=>array_keys($post['refund']),'returned_quantity'=>$qty,'recorded_payout'=>$paid,'stock_before'=>10,'stock_after'=>$stock]);
 }
 public function testAuditLegacyReceiveReplayAddsTwice():void {
  $post=['product_id'=>1,'branch_id'=>1,'stock'=>5,'old_stock'=>10,'stock_mode'=>'receive','remarks'=>'Audit receive retry'];
  foreach([1,2] as $attempt)$this->fixture('controller',\App\Controllers\Products::class,$post)->updateStock();
  $stock=(int)$this->fixture('row','branch_products')['stock'];$logs=$this->db->table('stock_logs')->countAllResults();$this->assertSame(20,$stock);$this->assertSame(2,$logs);
  $this->evidence('receive-replay',['initial_stock'=>10,'same_request_quantity'=>5,'attempts'=>2,'final_stock'=>$stock,'movement_logs'=>$logs]);
 }
 public function testAuditDigitalTenderAllowsOverpaymentAndNoReference():void {
  $post=$this->fixture('checkoutPost',['payment_method'=>'gcash','amount_paid'=>'100','reference_no'=>'']);
  $this->fixture('controller',\App\Controllers\Cashier\SalesController::class,$post)->checkout();
  $sale=$this->db->table('sales')->where('id',2)->get()->getRowArray();$this->assertNotNull($sale);$this->assertSame(10.0,(float)$sale['final_total']);$this->assertSame(90.0,(float)$sale['change_amount']);$this->assertNull($sale['reference_no']);
  $this->evidence('digital-overpay',array_intersect_key($sale,array_flip(['id','final_total','payment_method','amount_paid','change_amount','reference_no'])));
 }
 public function testAuditExchangeDoesNotApplyMatchingProductPromotion():void {
  $input=$this->fixture('exchangeInput');
  $discount=['id'=>1,'discount_name'=>'Audit replacement promo','discount_type'=>'percentage','discount_value'=>20,'applies_to'=>'product','product_id'=>100,'minimum_purchase'=>0,'status'=>'active'];
  $this->db->table('discounts')->insert($discount);
  $quote=(new \App\Libraries\ProductExchange($this->db))->quote(1,$input,1,'admin');
  $pos=\App\Libraries\ProductPromotion::all([$discount],[['product_id'=>100,'category_id'=>1,'subtotal'=>30]],date('Y-m-d'));
  $this->assertSame(6.0,\App\Libraries\ProductPromotion::total($pos));$this->assertSame(0,$quote['discount']);$this->assertSame(3000,$quote['total']);
  $this->evidence('exchange-promo',['replacement_gross'=>30,'matching_pos_promo'=>6,'exchange_promo'=>$quote['discount']/100,'exchange_total'=>$quote['total']/100,'discount_selection'=>$input['discount_id']]);
 }
 public function testAuditQuantityReductionRestocksExpiredUnits():void {
  $this->db->table('branch_products')->where('id',1)->update(['expiration_date'=>'2000-01-01']);
  $this->db->table('sale_items')->where('id',1)->update(['expiration_date_at_sale'=>'2000-01-01']);
  $post=$this->fixture('correctionPost',['items'=>[1=>3]]);
  $this->fixture('controller',\App\Controllers\Admin\SaleCorrection::class,$post)->update(1);
  $this->assertSame(12,(int)$this->fixture('row','branch_products')['stock']);$this->assertSame(3,(int)$this->fixture('row','sale_items')['quantity']);
  $this->evidence('correction-expired',['original_sold_quantity'=>5,'corrected_quantity'=>3,'expired_on'=>'2000-01-01','stock_before'=>10,'stock_after'=>(int)$this->fixture('row','branch_products')['stock']]);
 }
 public function testAuditReplacementWithSameExpiryAllowsOldQuarantineRelease():void {
  $quarantine=$this->fixture('quarantineFixture');
  $service=new \App\Libraries\ExpiryStockResolution($this->db);$row=$service->inventory(1,'admin',1);
  $service->resolve(1,'replace',['outcome'=>'supplier_return','new_quantity'=>20,'new_expiry'=>'2099-01-01','reason'=>'Audit different delivery','confirmed'=>'1'],'admin',1,1,str_repeat('e',48),$service::revision($row));
  $this->assertSame(str_repeat('a',32),$this->fixture('row','branch_products')['stock_delivery_id']);
  $record=$quarantine->item(1,1);$this->assertNull($quarantine::releaseProblem($record));
  $this->assertTrue($quarantine->resolve(1,1,$this->fixture('quarantineInput'),$quarantine::revision($record)));
  $this->assertSame(22,(int)$this->fixture('row','branch_products')['stock']);
  $this->evidence('replacement-quarantine',['original_delivery'=>str_repeat('a',32),'replacement_delivery'=>$this->fixture('row','branch_products')['stock_delivery_id'],'same_expiry'=>'2099-01-01','replacement_quantity'=>20,'old_return_released'=>2,'stock_after'=>22]);
 }
 public function testAuditOrdinaryProductEditCanChangeExpiryWithoutExpiryMovement():void {
  $post=$this->fixture('expiryProductEditPost');
  $this->db->table('branch_products')->where('id',1)->update(['expiration_date'=>'2000-01-01']);
  $this->fixture('controller',\App\Controllers\Products::class,$post)->update(1);
  $this->assertSame('2099-01-01',$this->fixture('row','branch_products')['expiration_date']);
  $this->assertSame(10,(int)$this->fixture('row','branch_products')['stock']);$this->assertSame(0,$this->db->table('stock_logs')->countAllResults());
  $this->evidence('edit-expiry',['old_expiry'=>'2000-01-01','new_expiry'=>$this->fixture('row','branch_products')['expiration_date'],'stock'=>10,'stock_movements'=>0,'activity'=>$this->fixture('row','activity_logs')['activity']]);
 }
}
