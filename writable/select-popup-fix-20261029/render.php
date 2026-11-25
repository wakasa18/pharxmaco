<?php
require dirname(__DIR__,2) . '/system/Test/bootstrap.php';
require_once dirname(__DIR__,2) . '/tests/unit/BusinessLogicTest.php';
$test = new BusinessLogicTest('testProductListFiltersUseBranchQuantityAndInclusiveReorderBoundary');
(new ReflectionMethod($test,'setUp'))->invoke($test);
try {
    (new ReflectionMethod($test,'productListFilterFixture'))->invoke($test);
    helper(['url','form']);
    session()->set(['role'=>'cashier','branch_id'=>1,'full_name'=>'Preview Staff']);
$sale = [
    'id' => 1, 'invoice_no' => 'INV-20260917-101805-D0360', 'sale_date' => '2026-09-17 10:18:05',
    'branch_name' => 'Main Branch', 'branch_address' => 'Santa Maria, Bulacan', 'branch_contact' => '09123456789',
    'full_name' => 'System Administrator', 'payment_method' => 'cash', 'reference_no' => '',
    'total_amount' => 455, 'discount_amount' => 45.5, 'final_total' => 409.5, 'amount_paid' => 500,
    'change_amount' => 90.5, 'status' => 'completed', 'notes' => '',
];
$items = [
    ['id' => 1, 'product_name_snapshot' => 'Carbocisteine 500mg Capsule', 'quantity' => 2, 'price' => 125, 'subtotal' => 250, 'discount_applied' => 25],
    ['id' => 2, 'product_name_snapshot' => 'Ascorbic Acid + Zinc 500mg / 10mg Film-coated Tablet', 'quantity' => 5, 'price' => 41, 'subtotal' => 205, 'discount_applied' => 20.5],
];

    $previewItems = array_map(static fn($item)=>$item+['already_refunded'=>0,'refundable_qty'=>$item['quantity'],'refund_unit_price'=>$item['price']*0.9,'refundable_amount'=>$item['subtotal']-$item['discount_applied']],$items);
    foreach (['error'=>true,'clean'=>false] as $name=>$error) {
        \Config\Services::resetSingle('renderer');
        session()->remove('error');
        if ($error) session()->setFlashdata('error','The request could not be completed. Review the entered details.');
        $html=view('cashier/sales/refund_partial',['sale'=>$sale,'items'=>$previewItems,'refundHistory'=>[],'refundToken'=>'synthetic-token']);
        $html=preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html);
        $html=preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~','',$html);
        file_put_contents(__DIR__.'/'.$name.'.html',$html);
        if ($error) file_put_contents(__DIR__.'/before.html',str_replace('/assets/css/select-picker.css?v=20261029-select-popup','/select-picker-before.css',$html));
    }
    echo "Rendered refund error/clean fixtures with isolated SQLite data.\n";
} finally { (new ReflectionMethod($test,'tearDown'))->invoke($test); }
