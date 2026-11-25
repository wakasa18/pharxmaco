<?php

trait ShiftPrintCases
{
    public function testShiftPrintIncludesEveryMovementAndKeepsClosedFinancialSnapshot(): void
    {
        $service=$this->cashierSetup();$shift=$service->activeFor(1);
        $this->db->transBegin();
        for($i=1;$i<=120;$i++) $service->event($shift,'print-sale:'.$i,'sale','cash',100,$i+1000,
            ['invoice'=>'PRINT-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'discount'=>0,'vat_relief'=>0,
             'products'=>[['id'=>1,'name'=>'Print test item','quantity'=>1,'amount'=>1,'direction'=>'sold']]]);
        $this->db->transCommit();
        $summary=$service->summary($shift);
        $this->assertSame(120,$summary['transactions']);
        $this->assertCount(100,$summary['activity'],'The live My Day list keeps its existing limit.');
        $this->assertCount(120,$service->printReport($shift)['activity']);
        $this->db->table('users')->where('id',2)->update(['role'=>'admin']);
        $service->close(1,2,'220','100','Manager closed on behalf of cashier',$summary['revision'],null);
        $saved=$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray();
        $report=$service->printReport($saved);
        $this->assertSame(json_decode($saved['summary_json'],true),$report['summary']);
        $this->assertCount(120,$report['activity']);
        $this->assertSame('PRINT-000001',$report['activity'][0]['invoice']);
        $this->assertSame('PRINT-000120',$report['activity'][119]['invoice']);
        $html=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,[])->printShift(1);
        $this->assertStringContainsString('PRINT-000001',$html);
        $this->assertStringContainsString('PRINT-000120',$html);
        $this->assertStringContainsString('All 120 movements',$html);
        $this->assertStringContainsString('<dt>Closed by</dt><dd>Next Cashier</dd>',$html);
        $this->assertLessThan(strpos($html,'Complete shift activity'),strpos($html,'Cashier signature'));
        $this->assertSame($saved['summary_json'],$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray()['summary_json']);
        session()->set(['user_id'=>3,'role'=>'cashier','branch_id'=>2]);
        $this->assertSame(404,$this->controller(\App\Controllers\Cashier\WorkspaceController::class,[])->printShift(1)->getStatusCode());
    }

    public function testShiftPrintShowsOpeningDifferenceAndPreservesLongEscapedNotes(): void
    {
        $service=$this->cashierSetup();$shift=$service->activeFor(1);
        $service->close(1,1,'100','75','Previous cashier completed handover',$service->summary($shift)['revision'],2);
        $note='<script>alert("note")</script> '.str_repeat('Physical count checked by both cashiers. ',10).'End of complete note.';
        $id=$service->open(2,1,'70',str_repeat('d',32),$note);
        session()->set(['user_id'=>2,'role'=>'cashier','branch_id'=>1]);
        $controller=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,[]);
        $html=$controller->printShift($id);
        $this->assertStringContainsString('Previous cash left / expected',$html);
        $this->assertStringContainsString('Short ₱5.00',$html);
        $this->assertStringContainsString('Notes continuation',$html);
        $this->assertStringContainsString(esc($note),$html);
        $this->assertStringNotContainsString('<script>alert',$html);
        $this->assertStringContainsString('End of complete note.',$html);
        $this->assertStringContainsString('Interim · Still open',$html);
        $this->assertStringContainsString('Not counted yet',$html);
        file_put_contents(WRITEPATH.'cashier-day-review/shift-print-notes.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
        $this->db->table('branches')->where('id',1)->update(['branch_name'=>'Branch "West" </style><script>alert("branch")</script>']);
        $unsafeName=$controller->printShift($id);
        $this->assertStringNotContainsString('</style><script>alert',$unsafeName,'Names cannot escape a page-margin CSS string.');
        $this->assertStringContainsString(esc('Branch "West" </style><script>alert("branch")</script>'),$unsafeName);
    }
}
