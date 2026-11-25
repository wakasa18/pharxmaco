<?php

trait ReturnPagesAccessCases
{
    private function returnAccessFixture(): void
    {
        $this->quarantineFixture();
        $this->db->table('branches')->where('id', 1)->update(['branch_name' => 'Assigned Branch']);
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Private Other Branch', 'status' => 'active']);
        $this->db->table('users')->insert(['id' => 2, 'full_name' => 'Branch Cashier', 'username' => 'branch-cashier', 'password' => 'hashed', 'role' => 'cashier', 'branch_id' => 1, 'status' => 'active']);
        $sale = $this->row('sales'); $this->db->table('sales')->insert(array_replace($sale, ['id' => 2, 'invoice_no' => 'PRIVATE-OTHER-INVOICE', 'branch_id' => 2]));
        $item = $this->row('sale_items'); $this->db->table('sale_items')->insert(array_replace($item, ['id' => 2, 'sale_id' => 2]));
        $this->addDamagedReturn(2, ['sale_id' => 2, 'sale_item_id' => 2, 'product_name_snapshot' => 'Private quarantine product']);
        $this->addDamagedReturn(3, ['return_condition' => 'damaged', 'product_name_snapshot' => 'Assigned damaged product']);
        $this->addDamagedReturn(4, ['sale_id' => 2, 'sale_item_id' => 2, 'return_condition' => 'damaged', 'product_name_snapshot' => 'Private damaged product']);
        session()->set(['user_id' => 2, 'role' => 'cashier', 'branch_id' => 1]);
    }

    private function returnAccessRender(string $kind, string $action = 'index', array $get = [], ?int $id = null)
    {
        \Config\Services::resetSingle('renderer');
        $class = $kind === 'quarantine' ? \App\Controllers\Admin\QuarantineController::class : \App\Controllers\Admin\DamagedItemsController::class;
        $controller = $this->controller($class, []); service('request')->setGlobal('get', $get); service('request')->setMethod('GET');
        return $id === null ? $controller->$action() : $controller->$action($id);
    }

    public function testCashierReturnPagesEnforceAssignedBranchDespiteForgedFiltersOrSession(): void
    {
        $this->returnAccessFixture();
        $quarantine = new \App\Libraries\Quarantine($this->db); $damage = new \App\Libraries\DamagedItems($this->db);
        foreach ([0, 1, 2, 999] as $requested) {
            $this->assertSame([1], array_map('intval', array_column($quarantine->records(2, 'all', $requested), 'id')));
            $this->assertSame([3], array_map('intval', array_column($damage->records(2, 'all', $requested), 'id')));
        }
        session()->set('branch_id', 2);
        foreach (['quarantine', 'damaged'] as $kind) {
            $html = $this->returnAccessRender($kind, 'index', ['branch_id' => '2']);
            $this->assertStringContainsString('Assigned Branch', $html);
            $this->assertStringNotContainsString('Private Other Branch', $html);
            $this->assertStringNotContainsString('PRIVATE-OTHER-INVOICE', $html);
            $this->assertStringNotContainsString('All branches</option>', $html);
        }
        $this->assertCount(2, $quarantine->records(1, 'all'));
        $this->assertCount(2, $damage->records(1));
    }

    public function testCashierReturnDetailsDoNotExposeOtherBranchByRecordId(): void
    {
        $this->returnAccessFixture();
        foreach ([[new \App\Libraries\Quarantine($this->db), 2], [new \App\Libraries\DamagedItems($this->db), 4]] as [$service, $id]) {
            try { $service->item($id, 2); $this->fail('Other branch detail exposed'); }
            catch (\InvalidArgumentException $e) { $this->assertStringContainsString('not found', $e->getMessage()); }
        }
        $this->assertSame(302, $this->returnAccessRender('quarantine', 'show', [], 2)->getStatusCode());
        $this->assertSame(404, $this->returnAccessRender('damaged', 'show', [], 4)->getStatusCode());
        $this->assertStringContainsString('Quarantined return', $this->returnAccessRender('quarantine', 'show', [], 1));
        $this->assertStringContainsString('Assigned damaged product', $this->returnAccessRender('damaged', 'show', [], 3));
    }

    public function testCashierReturnReadsFailClosedForMissingInactiveOrChangedAssignment(): void
    {
        $this->returnAccessFixture();
        foreach ([['branch_id' => null], ['branch_id' => 999], ['branch_id' => 1, 'status' => 'inactive']] as $values) {
            $this->db->table('users')->where('id', 2)->update($values);
            foreach (['quarantine', 'damaged'] as $kind) $this->assertSame(403, $this->returnAccessRender($kind)->getStatusCode());
        }
        // The current stored assignment is authoritative even if an older session says branch 1.
        $this->db->table('users')->where('id', 2)->update(['branch_id' => 2, 'status' => 'active']);
        $this->assertSame([2], array_map('intval', array_column((new \App\Libraries\Quarantine($this->db))->records(2), 'id')));
        $this->assertSame([4], array_map('intval', array_column((new \App\Libraries\DamagedItems($this->db))->records(2), 'id')));
    }

    public function testCashierQuarantineReadsNeverCreateDraftsAndCannotResolveWithStolenDraft(): void
    {
        $this->returnAccessFixture();
        session()->remove('quarantine_drafts');
        $html = $this->returnAccessRender('quarantine', 'show', [], 1);
        $this->assertNull(session('quarantine_drafts'));
        $this->assertStringNotContainsString('name="inspection_token"', $html);
        $this->assertStringNotContainsString('name="outcome"', $html);
        $this->assertStringNotContainsString('Save inspection', $html);
        $this->assertStringContainsString('Awaiting administrator inspection', $html);
        $token = str_repeat('b', 48); $quarantine = new \App\Libraries\Quarantine($this->db);
        session()->set('quarantine_drafts', [$token => ['id' => 1, 'revision' => \App\Libraries\Quarantine::revision($quarantine->item(1, 1)), 'time' => time()]]);
        $response = $this->controller(\App\Controllers\Admin\QuarantineController::class, $this->quarantineInput() + ['inspection_token' => $token])->save(1);
        $this->assertSame(403, $response->getStatusCode());
        session()->set('role', 'admin');
        $forged = $this->controller(\App\Controllers\Admin\QuarantineController::class, $this->quarantineInput() + ['inspection_token' => $token])->save(1);
        $this->assertSame(403, $forged->getStatusCode());
        try { $quarantine->resolve(1, 2, $this->quarantineInput(), ''); $this->fail('Cashier release accepted'); }
        catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
    }

    public function testCashierReturnPagesShowRecordedInspectionAndProduceBrowserFixtures(): void
    {
        $this->returnAccessFixture();
        $cashierQuarantine = $this->returnAccessRender('quarantine');
        $cashierPending = $this->returnAccessRender('quarantine', 'show', [], 1);
        $cashierDamaged = $this->returnAccessRender('damaged');
        $cashierDamageDetail = $this->returnAccessRender('damaged', 'show', [], 3);
        $this->assertStringContainsString('View details', $cashierQuarantine);
        $this->assertStringNotContainsString('Inspect return</a>', $cashierQuarantine);
        $quarantine = new \App\Libraries\Quarantine($this->db);
        $quarantine->resolve(1, 1, $this->quarantineInput('damaged'), \App\Libraries\Quarantine::revision($quarantine->item(1, 1)));
        $cashierInspected = $this->returnAccessRender('quarantine', 'show', [], 1);
        $cashierInspectedDamage = $this->returnAccessRender('damaged', 'show', [], 1);
        $this->assertStringContainsString('Recorded outcome', $cashierInspected);
        $this->assertStringContainsString('Inspection note', $cashierInspected);
        $this->assertStringNotContainsString('name="outcome"', $cashierInspected);
        $this->assertStringContainsString('View quarantine inspection', $cashierInspectedDamage);
        session()->set(['user_id' => 1, 'role' => 'admin', 'branch_id' => 1]);
        $adminQuarantine = $this->returnAccessRender('quarantine');
        $adminPending = $this->returnAccessRender('quarantine', 'show', [], 2);
        $this->assertStringContainsString('name="outcome"', $adminPending);
        $this->assertStringContainsString('All branches</option>', $adminQuarantine);
        if (getenv('PHX_RETURN_ACCESS_FIXTURES') === '1') {
            $dir = WRITEPATH . 'return-access-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach (compact('cashierQuarantine', 'cashierPending', 'cashierDamaged', 'cashierDamageDetail', 'cashierInspected', 'cashierInspectedDamage', 'adminQuarantine', 'adminPending') as $name => $html) file_put_contents($dir . '/' . $name . '.html', $html);
        }
    }
}
