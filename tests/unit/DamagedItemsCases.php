<?php

use App\Controllers\Admin\DamagedItemsController;
use App\Libraries\DamagedItems;

trait DamagedItemsCases
{
    private function damagedFixture(): DamagedItems
    {
        $this->quarantineFixture();
        $this->db->table('refund_items')->where('id', 1)->update(['return_condition' => 'damaged']);
        return new DamagedItems($this->db);
    }

    private function addDamagedReturn(int $id, array $values = []): void
    {
        $row = $this->row('refund_items');
        $this->db->table('refund_items')->insert(array_replace($row, ['id' => $id, 'refund_event_id' => str_pad(dechex($id), 32, '0', STR_PAD_LEFT)], $values));
    }

    private function damagedRender(string $action = 'index', array $get = [], ?int $id = null)
    {
        \Config\Services::resetSingle('renderer');
        $controller = $this->controller(DamagedItemsController::class, []);
        service('request')->setGlobal('get', $get);
        service('request')->setMethod('GET');
        return $id === null ? $controller->$action() : $controller->$action($id);
    }

    public function testDamagedItemsCombineRefundExchangeAndQuarantineWithoutDuplicatesOrWrites(): void
    {
        $service = $this->damagedFixture();
        $this->addDamagedReturn(2);
        $event = str_pad('2', 32, '0', STR_PAD_LEFT);
        $sale = $this->row('sales');
        $this->db->table('sales')->insert(array_replace($sale, ['id' => 2, 'invoice_no' => 'EXC-1-' . $event]));
        $this->addDamagedReturn(3, ['return_condition' => 'quarantined']);
        $quarantine = new \App\Libraries\Quarantine($this->db);
        $quarantine->resolve(3, 1, $this->quarantineInput('damaged'), \App\Libraries\Quarantine::revision($quarantine->item(3, 1)));
        $before = [];
        foreach (['products', 'branch_products', 'sales', 'sale_items', 'refund_items', 'stock_logs', 'activity_logs', 'quarantine_resolutions'] as $table) $before[$table] = $this->db->table($table)->orderBy('id')->get()->getResultArray();
        $records = $service->records(1);
        $this->assertCount(3, $records);
        $this->assertSame([3, 2, 1], array_map(static fn ($r) => (int) $r['id'], $records));
        $this->assertSame(['quarantine', 'exchange', 'refund'], array_column($records, 'source'));
        $this->assertSame(6, array_sum(array_column($records, 'damaged_quantity')));
        foreach (['refund', 'exchange', 'quarantine'] as $source) $this->assertCount(1, $service->records(1, $source));
        $this->assertSame('quarantined', $service->item(3, 1)['return_condition']);
        $this->assertSame('damaged', $service->item(3, 1)['inspection_outcome']);
        foreach ($before as $table => $rows) $this->assertSame($rows, $this->db->table($table)->orderBy('id')->get()->getResultArray(), $table . ' must not change');
    }

    public function testDamagedItemsExcludeOtherConditionsAndOutcomesAndRespectBranch(): void
    {
        $service = $this->damagedFixture();
        foreach (['resellable', 'expired', 'quarantined'] as $offset => $condition) $this->addDamagedReturn($offset + 2, ['return_condition' => $condition]);
        $quarantine = new \App\Libraries\Quarantine($this->db);
        $quarantine->resolve(4, 1, $this->quarantineInput('expired'), \App\Libraries\Quarantine::revision($quarantine->item(4, 1)));
        $this->addDamagedReturn(5, ['return_condition' => 'quarantined']);
        $this->addDamagedReturn(6, ['return_condition' => 'quarantined']);
        $quarantine->resolve(6, 1, $this->quarantineInput('released'), \App\Libraries\Quarantine::revision($quarantine->item(6, 1)));
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Branch B', 'status' => 'inactive']);
        $sale = $this->row('sales'); $this->db->table('sales')->insert(array_replace($sale, ['id' => 2, 'invoice_no' => 'OTHER-BRANCH', 'branch_id' => 2]));
        $this->addDamagedReturn(7, ['sale_id' => 2]);
        $this->assertCount(2, $service->records(1));
        $this->assertSame(1, (int) $service->records(1, 'all', 1)[0]['id']);
        $this->assertSame(7, (int) $service->records(1, 'all', 2)[0]['id']);
        $this->assertCount(0, $service->records(1, 'all', 3));
        foreach ([2, 3, 4, 5, 6, 999] as $id) {
            try { $service->item($id, 1); $this->fail('Unrelated return exposed: ' . $id); }
            catch (\InvalidArgumentException $e) { $this->assertSame('Damaged return not found.', $e->getMessage()); }
        }
    }

    public function testDamagedItemsExchangeClassificationUsesEventAndBranchInsteadOfNotes(): void
    {
        $service = $this->damagedFixture();
        $this->db->table('refund_items')->where('id', 1)->update(['reason' => 'Exchange to FAKE. No linked exchange invoice.']);
        $this->assertSame('refund', $service->item(1, 1)['source']);
        $sale = $this->row('sales');
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Branch B', 'status' => 'active']);
        $this->db->table('sales')->insert(array_replace($sale, ['id' => 2, 'branch_id' => 2, 'invoice_no' => 'EXC-1-' . str_repeat('c', 32)]));
        $this->assertSame('refund', $service->item(1, 1)['source']);
        $this->db->table('sales')->where('id', 2)->update(['branch_id' => 1]);
        $this->assertSame('exchange', $service->item(1, 1)['source']);
        $this->db->table('refund_items')->where('id', 1)->update(['reason' => 'Ordinary reason']);
        $this->assertSame('exchange', $service->item(1, 1)['source']);
        $this->db->table('refund_items')->where('id', 1)->update(['refund_event_id' => null]);
        $this->assertSame('refund', $service->item(1, 1)['source']);
    }

    public function testDamagedItemsRemainReadableWithoutInspectionTableOrLiveInventory(): void
    {
        $service = $this->damagedFixture();
        $this->db->query('DROP TABLE quarantine_resolutions'); $this->db->resetDataCache();
        $this->db->table('branch_products')->where('id', 1)->delete();
        $this->db->table('products')->where('id', 1)->update(['status' => 'inactive', 'is_deleted' => 1]);
        $this->assertFalse($service->includesInspections());
        $this->assertCount(1, $service->records(1));
        $this->assertSame('refund', $service->item(1, 1)['source']);
        $html = $this->damagedRender();
        $this->assertStringContainsString('Showing items marked damaged at return', $html);
        $this->assertStringContainsString('View details', $html);
    }

    public function testDamagedItemsRejectInactiveUsersAndForgedFilters(): void
    {
        $service = $this->damagedFixture();
        foreach ([['role' => 'cashier', 'status' => 'inactive'], ['role' => 'admin', 'status' => 'inactive']] as $values) {
            $this->db->table('users')->where('id', 1)->update($values);
            try { $service->records(1); $this->fail('Unauthorized list'); } catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
            try { $service->item(1, 1); $this->fail('Unauthorized details'); } catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
        }
        $this->db->table('users')->where('id', 1)->update(['role' => 'admin', 'status' => 'active']);
        foreach ([['source' => []], ['source' => 'expired'], ['branch_id' => []], ['branch_id' => '-1'], ['branch_id' => '2147483648'], ['branch_id' => '1 OR 1=1']] as $get) $this->assertSame(400, $this->damagedRender('index', $get)->getStatusCode());
        session()->set('role', 'cashier');
        $this->db->table('users')->where('id', 1)->update(['role' => 'cashier']);
        $this->assertStringContainsString('Damaged Items', $this->damagedRender());
        $this->assertStringContainsString('Damaged item details', $this->damagedRender('show', [], 1));
    }

    public function testDamagedDetailsPreserveExactEventLinksEscapeNotesAndHaveNoStockAction(): void
    {
        $this->damagedFixture();
        $this->db->table('refund_items')->where('id', 1)->update(['reason' => '<script>window.damageInjected=1</script>']);
        $html = $this->damagedRender('show', [], 1);
        $this->assertStringContainsString('event=' . str_repeat('c', 32), html_entity_decode($html));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>window.damageInjected', $html);
        $this->assertStringNotContainsString('name="outcome"', $html);
        $this->assertStringNotContainsString('Save inspection', $html);
        $this->db->table('refund_items')->where('id', 1)->update(['refund_event_id' => null]);
        $legacy = $this->damagedRender('show', [], 1);
        $this->assertStringContainsString('ts=2026-10-23+09%3A00%3A00', html_entity_decode($legacy));
        $this->assertSame(404, $this->damagedRender('show', [], 999)->getStatusCode());
    }

    public function testDamagedItemsPageFixturesAndInspectionTrace(): void
    {
        $this->damagedFixture();
        for ($id = 2; $id <= 32; $id++) $this->addDamagedReturn($id, ['product_name_snapshot' => $id === 32 ? 'Distinctive damaged item' : 'Sample damaged product ' . $id]);
        $event = str_pad('2', 32, '0', STR_PAD_LEFT);
        $this->db->table('sales')->insert(array_replace($this->row('sales'), ['id' => 2, 'invoice_no' => 'EXC-1-' . $event]));
        $this->db->table('refund_items')->where('id', 3)->update(['return_condition' => 'quarantined']);
        $quarantine = new \App\Libraries\Quarantine($this->db);
        $quarantine->resolve(3, 1, $this->quarantineInput('damaged'), \App\Libraries\Quarantine::revision($quarantine->item(3, 1)));
        $list = $this->damagedRender();
        $refund = $this->damagedRender('show', [], 1);
        $exchange = $this->damagedRender('show', [], 2);
        $quarantined = $this->damagedRender('show', [], 3);
        $this->assertStringContainsString('js-datatable', $list);
        $this->assertStringContainsString('32 records', $list);
        $this->assertStringContainsString('64 units', $list);
        $this->assertStringContainsString('View exchange return slip', $exchange);
        $this->assertStringContainsString('View quarantine inspection', $quarantined);
        $this->assertStringContainsString('Original return condition', $quarantined);
        $empty = $this->damagedRender('index', ['branch_id' => '999']);
        $this->assertStringContainsString('No damaged items match these filters.', $empty);
        $this->db->query('DROP TABLE quarantine_resolutions'); $this->db->resetDataCache();
        $legacy = $this->damagedRender();
        if (getenv('PHX_DAMAGED_FIXTURES') === '1') {
            $dir = WRITEPATH . 'damaged-items-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach (compact('list', 'refund', 'exchange', 'quarantined', 'empty', 'legacy') as $name => $html) file_put_contents($dir . '/' . $name . '.html', $html);
        }
    }
}
