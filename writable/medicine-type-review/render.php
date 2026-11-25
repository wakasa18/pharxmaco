<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url','form']);
session()->set(['role'=>'admin','branch_id'=>null,'full_name'=>'Preview Staff']);
$types = ['branded','generic',null,'non_medicine'];
$names = ['Biogesic 500mg','Paracetamol 500mg Tablet','Named medicine without a confirmed type','Sterile gauze pads'];
$products = [];
foreach ($types as $i=>$type) {
    $products[] = ['id'=>$i+1,'product_id'=>$i+1,'product_name'=>$names[$i],'generic_name'=>$type==='non_medicine'?null:'Paracetamol',
        'medicine_type'=>$type,'strength'=>'500 mg','tax_treatment'=>'taxable','sku'=>'MED-00'.($i+1),
        'category_id'=>1,'category_name'=>'Medicines','price'=>50,'cost_price'=>20,'stock'=>20,'quantity'=>1,'subtotal'=>50,
        'expiration_date'=>'2099-01-01','status'=>'active','branch_id'=>1,'unit'=>'box',
        'manufacturer'=>null,'supplier_name'=>null,'branch_name'=>'Main Branch','reorder_level'=>1];
}
$views = [
    'pos'=>['cashier/sales/index',['products'=>$products,'cart'=>[],'discounts'=>[],'heldSales'=>[]]],
    'products'=>['products/index',['products'=>$products,'keyword'=>'','medicineType'=>'','branchId'=>'','categoryId'=>'','status'=>'','stockFilter'=>'']],
    'catalog'=>['products/catalog',['branch'=>['branch_name'=>'Main Branch'],'products'=>$products,'selected'=>null,'pager'=>null,'keyword'=>'','medicineType'=>'']],
    'catalog-selected'=>['products/catalog',['branch'=>['branch_name'=>'Main Branch'],'products'=>[],'selected'=>$products[0],'pager'=>null,'keyword'=>'','medicineType'=>'branded']],
    'create'=>['products/create',['categories'=>[['id'=>1,'category_name'=>'Medicines']],'branches'=>[['id'=>1,'branch_name'=>'Main Branch']],'suppliers'=>[],'isCashier'=>false]],
    'edit'=>['products/edit',['product'=>$products[0],'branchProduct'=>$products[0],'branchId'=>1,'categories'=>[['id'=>1,'category_name'=>'Medicines']],'branches'=>[['id'=>1,'branch_name'=>'Main Branch']],'suppliers'=>[]]],
    'edit-nonmedicine'=>['products/edit',['product'=>$products[3],'branchProduct'=>$products[3],'branchId'=>1,'categories'=>[['id'=>1,'category_name'=>'Medicines']],'branches'=>[['id'=>1,'branch_name'=>'Main Branch']],'suppliers'=>[]]],
];
foreach ($views as $name=>[$view,$data]) {
    $html = view($view,$data,['saveData'=>false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html);
    file_put_contents(__DIR__.'/'.$name.'.html',$html);
}
$config = config(\Config\ReceiptInvoice::class);
$oldTaxStatus = $config->taxStatus;
$config->taxStatus = 'vat';
$benefitProducts = $products;
foreach ($benefitProducts as $i => &$product) {
    $product['price'] = 112; $product['subtotal'] = 112; $product['senior_pwd_eligible'] = $i === 0 ? 1 : null;
}
unset($product);
$benefitCart = array_column(array_slice($benefitProducts, 0, 2), null, 'product_id');
$benefitHtml = view('cashier/sales/index', ['products' => $benefitProducts, 'cart' => $benefitCart, 'heldSales' => [],
    'discounts' => [['id' => 1, 'discount_name' => 'Product promotion', 'discount_type' => 'percentage', 'discount_value' => 10,
        'applies_to' => 'product', 'product_id' => 1, 'minimum_purchase' => 0, 'max_discount_amount' => null]]], ['saveData' => false]);
file_put_contents(__DIR__ . '/pos-benefits.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $benefitHtml));
$closedShiftHtml = view('cashier/sales/index', ['products' => $benefitProducts, 'cart' => $benefitCart,
    'heldSales' => [], 'discounts' => [], 'shiftTrackingEnabled' => true, 'cashierShift' => null], ['saveData' => false]);
file_put_contents(__DIR__ . '/pos-checkout-closed-shift.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $closedShiftHtml));
$combinedProducts = array_slice($benefitProducts,0,2);
$combinedProducts[0]['product_name']='Eligible medicine';
$combinedProducts[1]['product_name']='Promotional product';$combinedProducts[1]['price']=55;$combinedProducts[1]['subtotal']=55;$combinedProducts[1]['senior_pwd_eligible']=0;
foreach ([25,40] as $rate) {
    session()->set('checkout_token',bin2hex(random_bytes(16)));
    $combinedPromos=[['id'=>3,'discount_name'=>'Product promo','discount_type'=>'percentage','discount_value'=>20,'applies_to'=>'product','product_id'=>2,'minimum_purchase'=>0,'max_discount_amount'=>100],
        ['id'=>4,'discount_name'=>'Overlapping promo','discount_type'=>'percentage','discount_value'=>$rate,'applies_to'=>'product','product_id'=>1,'minimum_purchase'=>0,'max_discount_amount'=>100],
        ['id'=>5,'discount_name'=>'Basket offer','discount_type'=>'percentage','discount_value'=>10,'applies_to'=>'all','minimum_purchase'=>0,'max_discount_amount'=>null],
        ['id'=>6,'discount_name'=>'Category offer','discount_type'=>'fixed','discount_value'=>5,'applies_to'=>'category','category_id'=>1,'category_name'=>'Medicines','minimum_purchase'=>0,'max_discount_amount'=>null]];
    $html=view('cashier/sales/index',['products'=>$combinedProducts,'cart'=>array_column($combinedProducts,null,'product_id'),'discounts'=>$combinedPromos,'heldSales'=>[]],['saveData'=>false]);
    file_put_contents(__DIR__.'/combined-pos-'.$rate.'.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
}
$categoryProducts=$combinedProducts;
$categoryProducts[2]=array_replace($categoryProducts[1],['id'=>3,'product_id'=>3,'category_id'=>2,'product_name'=>'Unrelated category item','price'=>100,'subtotal'=>100]);
$categoryPromos=[
    ['id'=>3,'discount_name'=>'Product promo','discount_type'=>'percentage','discount_value'=>20,'applies_to'=>'product','product_id'=>2,'minimum_purchase'=>0,'max_discount_amount'=>100],
    ['id'=>4,'discount_name'=>'Overlapping promo','discount_type'=>'percentage','discount_value'=>25,'applies_to'=>'product','product_id'=>1,'minimum_purchase'=>0,'max_discount_amount'=>100],
    ['id'=>8,'discount_name'=>'Category offer','discount_type'=>'percentage','discount_value'=>25,'applies_to'=>'category','category_id'=>1,'minimum_purchase'=>167,'max_discount_amount'=>50],
];
session()->set('checkout_token',bin2hex(random_bytes(16)));
$html=view('cashier/sales/index',['products'=>$categoryProducts,'cart'=>array_column($categoryProducts,null,'product_id'),'discounts'=>$categoryPromos,'heldSales'=>[]],['saveData'=>false]);
file_put_contents(__DIR__.'/category-pos.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
$beneficiary = ['kind' => 'senior_citizen', 'name' => 'Test Beneficiary', 'id' => 'TEST-ID-123', 'verified' => true];
$sale = ['id' => 999, 'invoice_no' => 'PREVIEW-ONLY', 'receipt_metadata' => \App\Libraries\ReceiptInvoice::snapshot(['discount_name' => 'Senior Citizen (20%)'], $config, null, $beneficiary),
    'sale_date' => '2026-10-14 12:00:00', 'status' => 'completed', 'payment_method' => 'cash', 'total_amount' => 224, 'discount_amount' => 20,
    'final_total' => 192, 'amount_paid' => 224, 'change_amount' => 32, 'full_name' => 'Preview Staff', 'branch_name' => 'Main Branch', 'address' => 'Preview address'];
$receiptItems = [
    ['product_name_snapshot' => 'Eligible medicine', 'quantity' => 1, 'price' => 112, 'subtotal' => 112, 'discount_applied' => 20,
        'vat_exemption_amount' => 12, 'statutory_discount_kind' => 'senior_citizen', 'tax_treatment_snapshot' => 'taxable'],
    ['product_name_snapshot' => 'Regular item', 'quantity' => 1, 'price' => 112, 'subtotal' => 112, 'discount_applied' => 0, 'tax_treatment_snapshot' => 'taxable'],
];
$receiptHtml = view('cashier/sales/receipt', ['sale' => $sale, 'items' => $receiptItems], ['saveData' => false]);
file_put_contents(__DIR__ . '/receipt-benefits.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $receiptHtml));
foreach (['senior_citizen', 'pwd', 'plain'] as $kind) {
    $detailSale = $sale;
    $detailItems = $receiptItems;
    $detailBeneficiary = $kind === 'plain' ? null : array_replace($beneficiary, ['kind' => $kind]);
    $detailSale['receipt_metadata'] = \App\Libraries\ReceiptInvoice::snapshot(null, $config, null, $detailBeneficiary);
    if ($kind === 'plain') {
        $detailSale['discount_amount'] = 0; $detailSale['final_total'] = 224; $detailSale['change_amount'] = 0;
        foreach ($detailItems as &$item) { $item['discount_applied'] = 0; $item['vat_exemption_amount'] = 0; $item['statutory_discount_kind'] = null; }
        unset($item);
    }
    foreach ($detailItems as &$item) $item['line_total_after_discount'] = \App\Libraries\StatutoryDiscount::netItem($item);
    unset($item);
    $detailHtml = view('cashier/sales/details', ['sale' => $detailSale, 'items' => $detailItems, 'refundHistory' => []], ['saveData' => false]);
    file_put_contents(__DIR__ . '/sale-details-' . $kind . '.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $detailHtml));
}
$config->taxStatus = $oldTaxStatus;
echo 'Rendered medicine type forms, product list, catalog and POS, plus statutory checkout and receipt previews.' . PHP_EOL;
