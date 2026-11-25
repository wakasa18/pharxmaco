<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
require_once dirname(__DIR__, 2) . '/tests/unit/BusinessLogicTest.php';
$test = new BusinessLogicTest('testProductListFiltersUseBranchQuantityAndInclusiveReorderBoundary');
(new ReflectionMethod($test, 'setUp'))->invoke($test);
try {
    (new ReflectionMethod($test, 'productListFilterFixture'))->invoke($test);
    helper(['url', 'form']);
    session()->set(['role'=>'admin','branch_id'=>1,'user_id'=>1,'full_name'=>'Demo Admin']);
    foreach (['open'=>['id'=>10], 'closed'=>null] as $name=>$shift) {
        \Config\Services::resetSingle('renderer');
        $html=view('cashier/sales/index',['products'=>[],'cart'=>[],'discounts'=>[],'heldSales'=>[],
            'shiftTrackingEnabled'=>true,'cashierShift'=>$shift],['saveData'=>false]);
        $html=preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html);
        $html=preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~','',$html);
        file_put_contents(__DIR__.'/'.$name.'.html',$html);
        $old=str_replace(file_get_contents(__DIR__.'/after.css'),file_get_contents(__DIR__.'/before.css'),$html);
        if($old===$html) throw new RuntimeException('Before CSS not substituted.');
        file_put_contents(__DIR__.'/'.$name.'-before.html',$old);
    }
    echo "Rendered open/closed POS banner fixtures with isolated SQLite data.\n";
} finally { (new ReflectionMethod($test,'tearDown'))->invoke($test); }
