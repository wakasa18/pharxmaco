<?php
// Layout-only fixture: all records are in-memory SQLite, never the application database.
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
require ROOTPATH.'tests/unit/BusinessLogicTest.php';
$fixture=new BusinessLogicTest('testCashierWorkspaceRendersOperationalPagesAndAccessIsScoped');
$call=static fn(string $name,mixed ...$args)=>(new ReflectionMethod(BusinessLogicTest::class,$name))->invoke($fixture,...$args);
$call('setUp');
try {
    $service=$call('cashierSetup');$shift=$service->activeFor(1);
    $db=(new ReflectionProperty(BusinessLogicTest::class,'db'))->getValue($fixture);
    $db->transBegin();
    for($i=1;$i<=120;$i++) {
        $id=1+($i-1)%24;
        $service->event($shift,'layout-sale:'.$i,'sale','cash',100,$i+1000,
            ['invoice'=>'DEMO-SHIFT-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),
             'discount'=>0,'vat_relief'=>0,'products'=>[['id'=>$id,'name'=>'Demo medicine '.$id.' - extended product description and strength for line wrapping',
                'quantity'=>1,'amount'=>1,'direction'=>'sold']]]);
    }
    $db->transCommit();
    $summary=$service->summary($shift);
    $service->close(1,1,'220','100','Layout stress test, synthetic data only',$summary['revision'],2);
    $controller=$call('controller',\App\Controllers\Cashier\WorkspaceController::class,[]);
    $html=$controller->printShift(1);
    file_put_contents(WRITEPATH.'cashier-day-review/shift-print-busy.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
    file_put_contents(__DIR__.'/busy-counts.json',json_encode(['transactions'=>$summary['transactions'],'source_events'=>120,
        'live_activity_rows'=>count($summary['activity']),'printed_activity_rows'=>count($service->printReport($db->table('cashier_shifts')->where('id',1)->get()->getRowArray())['activity']),
        'products'=>count($summary['products'])],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "Rendered a synthetic 120-transaction shift using the actual report view.\n";
} finally {$call('tearDown');}
