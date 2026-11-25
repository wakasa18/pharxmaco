<?php

use App\Libraries\CashierShift;
use App\Libraries\CashierDayReport;

trait CashierWorkspaceCases
{
    public function testAdminWithoutAssignedBranchStartsShiftBeforeEnteringPos(): void
    {
        helper(['url', 'form']);
        $service = $this->cashierSetup(false);
        $this->db->table('users')->where('id', 1)->update(['branch_id' => null]);
        session()->set(['branch_id' => null, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($this->row('users'))]);
        $cart = [1 => ['product_id' => 1, 'quantity' => 1]];
        $held = [['cart' => $cart, 'label' => 'Waiting customer', 'held_at' => CashierShift::now()]];
        session()->set(['cart' => $cart, 'held_sales' => $held]);

        $pos = $this->controller(\App\Controllers\Cashier\SalesController::class, []);
        $this->assertSame(site_url('cashier/start-shift'), $pos->index()->getHeaderLine('Location'));
        $this->assertSame($cart, session('cart'));
        $this->assertSame($held, session('held_sales'));
        $this->assertSame(0, $this->db->table('cashier_held_sales')->countAllResults());
        $workspace = $this->controller(\App\Controllers\Cashier\WorkspaceController::class, []);
        $html = $workspace->start();
        $this->assertStringContainsString('<h1>Start Shift</h1>', $html);
        $this->assertStringContainsString('Branch A', $html);
        $post = ['drawer_id' => '1', 'opening_cash' => '100', 'open_key' => str_repeat('e', 32)];
        foreach ([1, 2] as $retry) {
            $this->assertSame(site_url('cashier/sales'), $this->controller(\App\Controllers\Cashier\WorkspaceController::class, $post)->open()->getHeaderLine('Location'));
        }
        $this->assertSame(site_url('cashier/sales'), $workspace->start()->getHeaderLine('Location'));
        $this->assertSame(1, $this->db->table('cashier_shifts')->countAllResults());
        $this->assertSame(1, CashierShift::operatingBranch($this->db));
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertNull(session('branch_id'));
        $this->assertNull($this->row('users')['branch_id']);

        // Closing retains the reporting branch, but POS still requires a new shift.
        $shift = $service->activeFor(1);
        $summary = $service->summary($shift);
        $service->close((int) $shift['id'], 1, '100', '100', '', $summary['revision'], null);
        $this->assertSame(1, CashierShift::operatingBranch($this->db));
        $this->assertSame(site_url('cashier/start-shift'), $pos->index()->getHeaderLine('Location'));
        $this->assertSame($cart, session('cart'));
        $this->assertSame(site_url('cashier/shifts/' . $shift['id']), $this->controller(\App\Controllers\Cashier\WorkspaceController::class, $post)->open()->getHeaderLine('Location'));
    }

    public function testAdminPosUsesChosenDrawerBranchWithoutChangingAccountAssignment(): void
    {
        helper(['url', 'form']);
        $this->retainedModels();
        $service = $this->cashierSetup(false);
        session()->remove(['cart', 'held_sales']);
        $this->db->table('users')->where('id', 1)->update(['branch_id' => null]);
        session()->set(['branch_id' => null, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($this->row('users'))]);
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Branch B', 'status' => 'active']);
        $this->db->table('cashier_drawers')->insert(['id' => 2, 'branch_id' => 2, 'drawer_name' => 'Main drawer', 'status' => 'active']);
        $this->db->table('products')->insert(['id' => 2, 'product_name' => 'Branch B exclusive medicine', 'stock' => 5, 'status' => 'active']);
        $this->db->table('branch_products')->insert(['id' => 2, 'product_id' => 2, 'branch_id' => 2, 'stock' => 5, 'price' => 15, 'cost_price' => 5, 'status' => 'active']);
        $post = ['drawer_id' => '2', 'opening_cash' => '100', 'open_key' => str_repeat('f', 32)];
        $this->assertSame(site_url('cashier/sales'), $this->controller(\App\Controllers\Cashier\WorkspaceController::class, $post)->open()->getHeaderLine('Location'));
        $this->assertSame(2, CashierShift::operatingBranch($this->db));
        \Config\Services::resetSingle('renderer'); // Simulate a fresh page request after Start Shift.
        $html = $this->controller(\App\Controllers\Cashier\SalesController::class, [])->index();
        $this->assertStringContainsString('Branch B exclusive medicine', $html);
        preg_match_all('~\bdata-pid="(\d+)"~', $html, $productCards);
        $this->assertSame(['2'], $productCards[1], 'POS product cards must come only from the chosen branch.');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertNull(session('branch_id'));
        $this->assertNull($this->row('users')['branch_id']);

        $this->controller(\App\Controllers\Cashier\SalesController::class, ['product_id' => '2', 'quantity' => '1'])->addToCart();
        $token = str_repeat('c', 32);
        session()->set('checkout_token', $token);
        $response = $this->controller(\App\Controllers\Cashier\SalesController::class, [
            '_checkout_token' => $token, 'items' => [2 => 1], 'payment_method' => 'cash', 'amount_paid' => '15',
        ])->checkout();
        $this->assertSame(site_url('cashier/sales/completed/2'), $response->getHeaderLine('Location'));
        $sale = $this->db->table('sales')->where('id', 2)->get()->getRowArray();
        $this->assertSame(2, (int) $sale['branch_id']);
        $this->assertSame(1, (int) $sale['user_id']);
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(4, (int) $this->db->table('branch_products')->where('id', 2)->get()->getRowArray()['stock']);
        $shift = $service->activeFor(1);
        $summary = $service->summary($shift);
        $this->assertSame(1, $summary['transactions']);
        $this->assertSame(115.0, $summary['expected_cash']);
        $service->close((int) $shift['id'], 1, '115', '100', '', $summary['revision'], null);
        $this->cashierReject(fn () => $service->open(2, 2, '100', str_repeat('a', 32)), 'assigned branch');
        session()->set(['user_id' => 2, 'role' => 'cashier', 'branch_id' => 1]);
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(\App\Controllers\Cashier\WorkspaceController::class, [])->start();
        $this->assertStringContainsString('Branch A', $html);
        $this->assertStringNotContainsString('Branch B', $html);
    }

    public function testCashierSignsInToStartPageAndContinuesToPosAfterOpening(): void
    {
        helper(['url','form']); $service=$this->cashierSetup(false);
        $this->db->table('users')->where('id',2)->update(['username'=>'shift_cashier','password'=>password_hash('StartShiftFixture!',PASSWORD_DEFAULT)]);
        \Config\Services::injectMock('cache',new \CodeIgniter\Cache\Handlers\DummyHandler(config(\Config\Cache::class)));
        $auth=$this->controller(\App\Controllers\Auth::class,['username'=>'shift_cashier','password'=>'StartShiftFixture!']);
        $this->assertSame(site_url('cashier/start-shift'),$auth->attemptLogin()->getHeaderLine('Location'));
        $this->assertTrue(\App\Libraries\AccountSession::check());
        session()->set('cart',[1=>['product_id'=>1,'quantity'=>1]]);
        $pos=$this->controller(\App\Controllers\Cashier\SalesController::class,[]);
        $this->assertSame(site_url('cashier/start-shift'),$pos->index()->getHeaderLine('Location'));
        $this->assertNotEmpty(session('cart'));
        $workspace=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,[]);
        $html=$workspace->start();
        $this->assertStringContainsString('<h1>Start Shift</h1>',$html);
        $this->assertStringContainsString('Start shift and open POS',$html);
        $this->assertStringNotContainsString('data-report-metric=',$html);
        $post=['drawer_id'=>'1','opening_cash'=>'bad','open_key'=>str_repeat('b',32)];
        $invalid=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,$post)->open();
        $this->assertSame(site_url('cashier/start-shift'),$invalid->getHeaderLine('Location'));
        $this->assertNull($service->activeFor(2));
        $post['opening_cash']='100';
        foreach ([1,2] as $retry) $this->assertSame(site_url('cashier/sales'),$this->controller(\App\Controllers\Cashier\WorkspaceController::class,$post)->open()->getHeaderLine('Location'));
        $this->assertSame(1,$this->db->table('cashier_shifts')->countAllResults());
        $this->assertSame(site_url('cashier/sales'),$workspace->start()->getHeaderLine('Location'));
        $this->assertSame(site_url('cashier/sales'),$auth->login()->getHeaderLine('Location'));
        $shift=$service->activeFor(2); $summary=$service->summary($shift);
        $service->close((int)$shift['id'],2,'100','100','',$summary['revision'],null);
        $this->assertSame(site_url('cashier/start-shift'),$auth->login()->getHeaderLine('Location'));
        $this->assertSame(site_url('cashier/start-shift'),$pos->index()->getHeaderLine('Location'));
        $stale=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,$post)->open();
        $this->assertSame(site_url('cashier/shifts/'.$shift['id']),$stale->getHeaderLine('Location'));
        $this->assertSame(1,$this->db->table('cashier_shifts')->countAllResults());
    }
    private function cashierSetup(bool $open = true): CashierShift
    {
        require_once APPPATH . 'Database/Migrations/2026_10_15_000014_add_cashier_shifts.php';
        $migration = new \App\Database\Migrations\AddCashierShifts(new \CodeIgniter\Database\SQLite3\Forge($this->db));
        $migration->up(); $migration->up();
        $this->assertSame(1, $this->db->table('cashier_drawers')->countAllResults());
        $this->db->table('users')->insert(['id'=>2,'full_name'=>'Next Cashier','role'=>'cashier','branch_id'=>1,'status'=>'active']);
        $this->db->table('users')->insert(['id'=>3,'full_name'=>'Other Cashier','role'=>'cashier','branch_id'=>2,'status'=>'active']);
        $service = new CashierShift($this->db);
        if ($open) $service->open(1, 1, '100.00', str_repeat('1',32));
        return $service;
    }
    private function cashierReject(callable $work, string $text): void
    {
        try { $work(); $this->fail('Invalid cashier action accepted: ' . $text); }
        catch (InvalidArgumentException $error) { $this->assertStringContainsString($text, $error->getMessage()); }
    }
    public function testCashierDrawerOwnershipHandoverAndCountedOpening(): void
    {
        $service = $this->cashierSetup(); $shift = $service->activeFor(1);
        $this->assertSame(1, $service->open(1,1,'100',str_repeat('1',32)));
        $this->cashierReject(fn()=> $service->open(2,1,'100',str_repeat('2',32)), 'in use');
        $summary = $service->summary($shift);
        $service->close(1,1,'100','75','Handover count',$summary['revision'],2);
        session()->set('branch_id',null);
        $this->assertSame(1,CashierShift::operatingBranch($this->db),'An administrator retains the reporting branch after closing.');
        session()->set('branch_id',1);
        $frozen = $service->summary($this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray());
        $this->cashierReject(fn()=> $service->open(3,1,'75',str_repeat('3',32)), 'assigned branch');
        $this->cashierReject(fn()=> $service->open(1,1,'75',str_repeat('4',32)), 'reserved');
        $this->cashierReject(fn()=> $service->open(2,1,'70',str_repeat('5',32)), 'reason');
        $id = $service->open(2,1,'70',str_repeat('5',32),'Five pesos missing at handover');
        $next = $service->activeFor(2); $this->assertSame($id,(int)$next['id']);
        $this->assertEquals(-5,$next['opening_difference']);
        $old = $this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray();
        $this->assertSame('accepted',$old['handover_status']); $this->assertSame($frozen,$service->summary($old));
        $this->assertNull($service->activeFor(1));
        $this->cashierReject(fn()=> $service->event($shift,'late','sale','cash',100,1), 'closed');
    }
    public function testCashierCloseDetectsNewMoneyRequiresReasonsAndFreezesReport(): void
    {
        $service=$this->cashierSetup(); $shift=$service->activeFor(1); $old=$service->summary($shift);
        $service->cashMovement(1,1,'cash_in','20','Float top-up',str_repeat('a',32));
        $service->cashMovement(1,1,'cash_in','20','Float top-up',str_repeat('a',32));
        $this->assertSame(1,$this->db->table('cashier_events')->countAllResults());
        $this->cashierReject(fn()=> $service->close(1,1,'120','100','',$old['revision'],null), 'New drawer activity');
        $summary=$service->summary($shift); $this->assertSame(120.0,$summary['expected_cash']);
        $this->cashierReject(fn()=> $service->close(1,1,'119','100','',$summary['revision'],null), 'reason');
        $this->cashierReject(fn()=> $service->cashMovement(1,1,'cash_out','121','Safe drop',str_repeat('b',32)), 'insufficient');
        $service->close(1,1,'119','100','Count short by one peso',$summary['revision'],null);
        $saved=$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray();
        $this->assertEquals(-1,$saved['cash_difference']); $this->assertEquals(19,$saved['banked_cash']);
        $service->close(1,1,'119','100','Repeated close',$summary['revision'],null);
        $service->open(2,1,'100',str_repeat('c',32));
        $service->cashMovement(2,1,'cash_out','10','Safe drop',str_repeat('d',32));
        $this->assertSame($saved['summary_json'],$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray()['summary_json']);
    }
    public function testCashierPersistentHeldCartsAreScopedAndBlockClosing(): void
    {
        $service=$this->cashierSetup(); $cart=[1=>['product_id'=>1,'quantity'=>2]];
        $id=$service->hold(1,1,$cart,'Customer waiting');
        $this->cashierReject(fn()=> $service->takeHeld(2,1,$id),'account and branch');
        $summary=$service->summary($service->activeFor(1));
        $this->cashierReject(fn()=> $service->close(1,1,'100','100','',$summary['revision'],null),'held sales');
        $this->assertSame($cart,$service->takeHeld(1,1,$id));
        $this->assertEmpty($service->held(1,1));
        session()->set('held_sales',[['cart'=>$cart,'label'=>'Older held cart','held_at'=>'2026-10-14 12:00:00']]);
        $service->importSessionHeld(1,1); $service->importSessionHeld(1,1);
        $this->assertCount(1,$service->held(1,1)); $this->assertNull(session('held_sales'));
        $this->cashierReject(fn()=> $service->review(2,'shift',1,'Checked'),'Administrator');
    }
    public function testCashierCheckoutRequiresShiftRecordsNetCashAndRetriesOnce(): void
    {
        $service=$this->cashierSetup(false);
        $this->db->query('ALTER TABLE sales ADD COLUMN checkout_token_hash TEXT');
        $this->db->query('CREATE UNIQUE INDEX checkout_token_unique ON sales(checkout_token_hash)');
        $post=$this->checkoutPost(['amount_paid'=>'50']);
        $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->assertSame(1,$this->db->table('sales')->countAllResults());
        $this->assertStringContainsString('Start a cashier shift',session()->getFlashdata('error'));
        $service->open(1,1,'100',str_repeat('1',32));
        $post=$this->checkoutPost(['amount_paid'=>'50']);
        $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $this->checkoutPost();
        $this->controller(\App\Controllers\Cashier\SalesController::class,$post)->checkout();
        $summary=$service->summary($service->activeFor(1));
        $this->assertSame(1,$summary['transactions']); $this->assertSame(10.0,$summary['sales']);
        $this->assertSame(110.0,$summary['expected_cash']); $this->assertSame(1,$this->db->table('cashier_events')->countAllResults());
        $this->assertSame(9,(int)$this->row('branch_products')['stock']);
    }
    public function testAjaxCheckoutShowsValidationWithoutConsumingFlashOrLosingRetryTokens(): void
    {
        $post = $this->checkoutPost(['amount_paid' => '1']);
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, $post);
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $controller->checkout();
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Location'));
        $this->assertFalse($data['success']);
        $this->assertSame('Insufficient payment.', $data['message']);
        $this->assertNull($data['redirect_url']);
        $this->assertSame(service('security')->getHash(), $data['csrf_hash']);
        $this->assertSame(session('checkout_token'), $data['checkout_token']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['checkout_token']);
        $this->assertNull(session()->getFlashdata('error'));
        $this->assertNotEmpty(session('cart'));
        $this->assertSame(1, $this->db->table('sales')->countAllResults());
        $post['amount_paid'] = '10';
        $post['_checkout_token'] = $data['checkout_token'];
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, $post);
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $retry = json_decode($controller->checkout()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($retry['success']);
        $this->assertSame(2, $this->db->table('sales')->countAllResults());
    }
    public function testCheckoutWithoutShiftPointsToStartForAdminAndCashierAndKeepsCart(): void
    {
        $this->cashierSetup(false);
        foreach ([1 => 'admin', 2 => 'cashier'] as $id => $role) {
            $branch = $role === 'admin' ? null : 1;
            $this->db->table('users')->where('id', $id)->update(['branch_id' => $branch]);
            session()->set(['user_id' => $id, 'role' => $role, 'branch_id' => $branch]);
            $post = $this->checkoutPost();
            $response = $this->controller(\App\Controllers\Cashier\SalesController::class, $post)->checkout();
            $this->assertSame(site_url('cashier/start-shift'), $response->getHeaderLine('Location'));
            $this->assertNotEmpty(session('cart'));
            $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, $this->checkoutPost());
            service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
            $response = $controller->checkout();
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(422, $response->getStatusCode());
            $this->assertFalse($data['success']);
            $this->assertSame(site_url('cashier/start-shift'), $data['redirect_url']);
            $this->assertStringContainsString('Start a cashier shift', $data['message']);
            $this->assertSame($data['message'], session()->getFlashdata('error'));
            $this->assertSame(1, $this->db->table('sales')->countAllResults());
            $this->assertSame(10, (int)$this->row('branch_products')['stock']);
            $this->assertSame(0, $this->db->table('cashier_shifts')->countAllResults());
        }
    }
    public function testAjaxCheckoutSuccessReturnsCompletedSaleAndRecordsDrawerOnce(): void
    {
        $service = $this->cashierSetup();
        $this->db->query('ALTER TABLE sales ADD COLUMN checkout_token_hash TEXT');
        $post = $this->checkoutPost(['amount_paid' => '50']);
        session()->setFlashdata('error', 'An earlier form failed.');
        $controller = $this->controller(\App\Controllers\Cashier\SalesController::class, $post);
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $controller->checkout();
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame(site_url('cashier/sales/completed/2'), $data['redirect_url']);
        $this->assertSame('Sale completed successfully.', $data['message']);
        $this->assertEmpty(session('cart'));
        $this->assertSame(2, $this->db->table('sales')->countAllResults());
        $this->assertSame(9, (int)$this->row('branch_products')['stock']);
        $summary = $service->summary($service->activeFor(1));
        $this->assertSame(1, $summary['transactions']);
        $this->assertSame(110.0, $summary['expected_cash']);
    }
    public function testCashierHeldResumeUsesCurrentPricesAndKeepsUnavailableCarts(): void
    {
        $service=$this->cashierSetup(); $cart=[1=>['product_id'=>1,'price'=>10,'quantity'=>2]];
        $id=$service->hold(1,1,$cart,'Customer waiting');
        $this->db->table('branch_products')->where('id',1)->update(['price'=>12,'stock'=>1]);
        $this->cashierReject(fn()=> $service->takeHeld(1,1,$id,false,true),'still saved');
        $this->assertCount(1,$service->held(1,1));
        $this->db->table('branch_products')->where('id',1)->update(['stock'=>5]);
        $resumed=$service->takeHeld(1,1,$id,false,true);
        $this->assertSame(12.0,$resumed[1]['price']); $this->assertSame(24.0,$resumed[1]['subtotal']);
        $this->assertEmpty($service->held(1,1));
    }
    public function testCashierRefundBelongsToOperatorAndDoesNotRewriteOriginalShift(): void
    {
        $service=$this->cashierSetup();
        $this->controller(\App\Controllers\Cashier\SalesController::class,$this->checkoutPost())->checkout();
        $summary=$service->summary($service->activeFor(1));
        $service->close(1,1,'110','100','Closing first cashier',$summary['revision'],2);
        $saved=$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray()['summary_json'];
        $service->open(2,1,'100',str_repeat('2',32));
        session()->set(['user_id'=>2,'role'=>'cashier','branch_id'=>1,'refund_token_2'=>'refund-workspace']);
        $item=$this->db->table('sale_items')->where('sale_id',2)->get()->getRowArray();
        $this->controller(\App\Controllers\Cashier\SalesController::class,['_refund_token'=>'refund-workspace','reason'=>'Customer return','refund_method'=>'cash','refund'=>[$item['id']=>1],'return_condition'=>[$item['id']=>'damaged']])->refundPartial(2);
        $summary=$service->summary($service->activeFor(2));
        $this->assertSame(10.0,$summary['refunds']); $this->assertSame(90.0,$summary['expected_cash']); $this->assertSame(0,$summary['transactions']);
        $this->assertSame($saved,$this->db->table('cashier_shifts')->where('id',1)->get()->getRowArray()['summary_json']);
        $today=substr(CashierShift::now(),0,10);
        $original=(new CashierDayReport($this->db))->forUser(1,1,$today,$today);
        $this->assertSame(1,$original['transactions']); $this->assertSame(10.0,$original['sales']);
        $this->assertSame(0.0,$original['refunds']);
    }
    public function testCashierExchangeCountsOnlyActualDifferenceAndRetryAfterClose(): void
    {
        $service=$this->cashierSetup(); $exchange=new \App\Libraries\ProductExchange($this->db);
        $input=$this->exchangeInput(['returns'=>[1=>2],'conditions'=>[1=>'quarantined'],'replacements'=>[100=>1],'settlement_method'=>'cash']);
        $quote=$exchange->quote(1,$input,1,'admin');
        $id=$exchange->complete(1,$input,1,'admin',1,str_repeat('e',48),$quote['revision']);
        $summary=$service->summary($service->activeFor(1));
        $this->assertSame(-10.0,$summary['net_collections']); $this->assertSame(90.0,$summary['expected_cash']);
        $this->assertSame(0,$summary['transactions']); $this->assertSame(1,$summary['exchange_count']);
        $service->close(1,1,'90','90','',$summary['revision'],null);
        $this->assertSame($id,$exchange->complete(1,$input,1,'admin',1,str_repeat('e',48),$quote['revision']));
        $this->assertSame(1,$this->db->table('cashier_events')->countAllResults());
    }
    public function testCashierLegacyCorrectionKeepsOriginalDayAndRecordsAdjustmentNow(): void
    {
        $service=$this->cashierSetup();
        $this->controller(\App\Controllers\Admin\SaleCorrection::class,$this->correctionPost())->update(1);
        $this->assertEquals(30,$this->row('sales')['final_total']);
        $summary=$service->summary($service->activeFor(1));
        $this->assertSame(-20.0,$summary['net_collections']); $this->assertSame(80.0,$summary['expected_cash']);
        $old=(new CashierDayReport($this->db))->forUser(1,1,'2026-09-14','2026-09-14');
        $this->assertSame(50.0,$old['sales']); $this->assertSame(5,$old['units_sold']); $this->assertSame(1,$old['transactions']);
        $service->review(1,'event',2,'Quantity correction checked');
        $this->assertSame(1,$this->db->table('cashier_event_reviews')->countAllResults());
        $this->cashierReject(fn()=> $service->review(1,'event',2,'Second review'), 'already');
    }
    public function testCashierPaymentReclassificationIsRestrictedToOriginalOpenShift(): void
    {
        helper(['url','form']); $service=$this->cashierSetup();
        $this->db->transBegin(); $service->recordSale($service->requireActive(1,1),1); $this->db->transCommit();
        $this->controller(\App\Controllers\Admin\SaleCorrection::class,$this->correctionPost(['items'=>[1=>5],'payment_method'=>'gcash','reference_no'=>'0012345678906']))->update(1);
        $summary=$service->summary($service->activeFor(1));
        $this->assertSame('gcash',$this->row('sales')['payment_method']);
        $this->assertSame(100.0,$summary['expected_cash']); $this->assertSame(50.0,$summary['payments']['gcash']['net']);
        $service->close(1,1,'100','100','',$summary['revision'],null);
        $service->open(1,1,'100',str_repeat('f',32));
        $result=$this->controller(\App\Controllers\Admin\SaleCorrection::class,$this->correctionPost(['items'=>[1=>5],'payment_method'=>'card','reference_no'=>'CARD-1']))->update(1);
        $this->assertStringContainsString('original open shift',$result);
        $this->assertSame('gcash',$this->row('sales')['payment_method']);
        $this->assertSame(0,$service->summary($service->activeFor(1))['transactions']);
    }
    public function testCashierDateScopeLegacyUnknownPayoutAndMoneyValidation(): void
    {
        $service=$this->cashierSetup(false);
        $this->db->table('refund_items')->insert(['sale_id'=>1,'sale_item_id'=>1,'product_id'=>1,'product_name_snapshot'=>'Test Item','quantity_refunded'=>1,'refund_subtotal'=>10,'refunded_by'=>1,'created_at'=>'2026-09-14 15:00:00']);
        $report=new CashierDayReport($this->db); $day=$report->forUser(1,1,'2026-09-14','2026-09-14');
        $this->assertSame(40.0,$day['net_collections']); $this->assertSame(10.0,$day['unknown_refund_amount']);
        $this->assertSame(10.0,$day['payments']['unknown']['paid_out']); $this->assertSame(1,$day['unidentified_refund_lines']);
        $this->assertSame(0,$report->forUser(2,1,'2026-09-14','2026-09-14')['transactions']);
        foreach ([['2026-02-30','2026-03-01'],['2026-09-01','2026-10-02'],['2026-10-02','2026-10-01']] as [$from,$to]) $this->cashierReject(fn()=> $report->forUser(1,1,$from,$to),'valid date range');
        foreach (['-1','1.001','1e3','100000000',[],null,'NaN'] as $value) $this->cashierReject(fn()=> CashierShift::money($value),'cash amount');
        $this->assertSame(9999999999,CashierShift::money('99999999.99'));
    }
    public function testCashierWorkspaceRendersOperationalPagesAndAccessIsScoped(): void
    {
        helper(['url','form']); $service=$this->cashierSetup();
        session()->set('full_name','Preview Cashier');
        $service->cashMovement(1,1,'cash_in','20','Extra change',str_repeat('9',32));
        $this->controller(\App\Controllers\Cashier\SalesController::class,$this->checkoutPost())->checkout();
        $workspace=$this->controller(\App\Controllers\Cashier\WorkspaceController::class,[]);
        $dir=WRITEPATH.'cashier-day-review/';
        if (!is_dir($dir)) mkdir($dir,0775,true);
        foreach (['pos-shift-open'=>$service->activeFor(1),'pos-shift-required'=>null] as $name=>$posShift) {
            $html=view('cashier/sales/index',['products'=>[],'cart'=>[],'discounts'=>[],'heldSales'=>[], 'cashierShift'=>$posShift,'shiftTrackingEnabled'=>true],['saveData'=>false]);
            file_put_contents($dir.$name.'.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
        }
        foreach (['my-day'=>$workspace->index(),'shift-open'=>$workspace->shift(1),'shift-print-open'=>$workspace->printShift(1),'manager'=>$workspace->manager()] as $name=>$html) {
            $this->assertIsString($html); $this->assertStringContainsString(str_starts_with($name,'shift-print')?'shift-print.css':'cashier-workspace.css',$html);
            file_put_contents($dir.$name.'.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
        }
        session()->set(['user_id'=>2,'role'=>'cashier']);
        $this->assertSame(404,$workspace->shift(1)->getStatusCode());
        $busy=$workspace->start();
        $this->assertStringContainsString('No drawer is available',$busy);
        file_put_contents($dir.'start-shift-busy.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$busy));
        $html=$workspace->index();
        file_put_contents($dir.'my-day-start.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
        session()->set(['user_id'=>1,'role'=>'admin']);
        $summary=$service->summary($service->activeFor(1));
        $service->close(1,1,'130','100','End of shift',$summary['revision'],2);
        foreach (['shift-closed'=>$workspace->shift(1),'shift-print-closed'=>$workspace->printShift(1)] as $name=>$html) {
            $this->assertStringContainsString('130.00',$html);
            file_put_contents($dir.$name.'.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$html));
        }
        $today=substr(CashierShift::now(),0,10);
        file_put_contents($dir.'report.json',json_encode((new CashierDayReport($this->db))->forUser(1,1,$today,$today),JSON_THROW_ON_ERROR));
        session()->set(['user_id'=>2,'role'=>'cashier']);
        file_put_contents($dir.'my-day-start.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$workspace->index()));
        $start=$workspace->start();
        $this->assertStringContainsString('<h1>Start Shift</h1>',$start);
        file_put_contents($dir.'start-shift.html',preg_replace('~https?://[^"\s]+/assets/~','/assets/',$start));
    }
}
