<?php

use App\Libraries\Quarantine;
use App\Controllers\Admin\QuarantineController;

trait QuarantineCases
{
    private function quarantineFixture(): Quarantine
    {
        require_once APPPATH . 'Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php';
        (new \App\Database\Migrations\AddQuarantineResolutions(new \CodeIgniter\Database\SQLite3\Forge($this->db)))->up();
        $this->db->table('refund_items')->insert(['id' => 1, 'sale_id' => 1, 'sale_item_id' => 1, 'product_id' => 1,
            'product_name_snapshot' => 'Test Item', 'quantity_refunded' => 2, 'price_at_sale' => 10, 'refund_subtotal' => 20,
            'refunded_by' => 1, 'reason' => 'Packaging needs inspection', 'return_condition' => 'quarantined',
            'refund_method' => 'cash', 'refund_event_id' => str_repeat('c', 32), 'created_at' => '2026-10-23 09:00:00']);
        return new Quarantine($this->db);
    }

    private function quarantineInput(string $outcome = 'released'): array
    {
        return ['outcome' => $outcome, 'note' => 'Inspected every unit and verified packaging, storage and expiry.', 'confirmed' => '1'];
    }

    public function testQuarantineReleaseUpdatesCorrectStockAndKeepsOriginalFinancialRecords(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('branches')->insert(['id' => 2, 'branch_name' => 'Branch B', 'status' => 'active']);
        $this->db->table('branch_products')->insert(['id' => 2, 'branch_id' => 2, 'product_id' => 1, 'stock' => 7, 'status' => 'active']);
        $before = [$this->row('sales'), $this->row('sale_items'), $this->row('refund_items')];
        $record = $service->item(1, 1);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput(), Quarantine::revision($record)));
        $this->assertSame(12, (int) $this->row('branch_products')['stock']);
        $this->assertSame(7, (int) $this->db->table('branch_products')->where('id', 2)->get()->getRowArray()['stock']);
        $this->assertSame(19, (int) $this->row('products')['stock']);
        $log = $this->row('stock_logs');
        $this->assertSame(['returned', 2, 10, 12], [$log['action_type'], (int) $log['quantity'], (int) $log['previous_stock'], (int) $log['new_stock']]);
        $this->assertStringContainsString('Q-1', $log['remarks']);
        $resolution = $service->item(1, 1)['resolution'];
        $this->assertSame('released', $resolution['outcome']);
        $this->assertSame((int) $log['id'], (int) $resolution['stock_log_id']);
        $this->assertSame($before, [$this->row('sales'), $this->row('sale_items'), $this->row('refund_items')]);
        $this->assertCount(0, $service->records(1));
        $this->assertCount(1, $service->records(1, 'released'));
        $this->assertFalse($service->resolve(1, 1, $this->quarantineInput(), Quarantine::revision($record)));
        $this->assertFalse($service->resolve(1, 1, $this->quarantineInput('expired'), Quarantine::revision($record)));
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
        $this->assertSame(1, $this->db->table('activity_logs')->countAllResults());
        $this->assertSame(12, (int) $this->row('branch_products')['stock']);
    }

    public function testQuarantineDamagedAndExpiredNeverDeductStockAgain(): void
    {
        $service = $this->quarantineFixture();
        foreach (['damaged', 'expired'] as $index => $outcome) {
            $id = $index + 1;
            if ($id > 1) { $refund = $this->row('refund_items'); $refund['id'] = $id; $this->db->table('refund_items')->insert($refund); }
            $this->assertTrue($service->resolve($id, 1, $this->quarantineInput($outcome), Quarantine::revision($service->item($id, 1))));
            $this->assertSame(10, (int) $this->row('branch_products')['stock']);
            $this->assertNull($service->item($id, 1)['resolution']['stock_log_id']);
            $this->assertSame('quarantined', $this->row('refund_items')['return_condition']);
        }
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertCount(2, $service->records(1, 'all'));
        $this->assertCount(0, $service->records(1, 'all', 2));
    }

    public function testQuarantineReleaseRechecksDeliveryExpiryActivityAndQuantityOnServer(): void
    {
        $service = $this->quarantineFixture();
        $original = $service->item(1, 1);
        $cases = [
            ['sale_items', ['stock_delivery_id' => null]],
            ['sale_items', ['expiration_date_at_sale' => null]],
            ['branch_products', ['stock_delivery_id' => str_repeat('b', 32)]],
            ['branch_products', ['expiration_date' => '2099-02-01']],
            ['sale_items', ['expiration_date_at_sale' => '2020-01-01']],
            ['branch_products', ['status' => 'inactive']],
            ['branch_products', ['is_deleted' => 1]],
            ['branch_products', ['stock' => -1]],
            ['branch_products', ['stock' => 2147483647]],
            ['products', ['status' => 'inactive']],
            ['products', ['is_permanently_deleted' => 1]],
            ['branches', ['status' => 'inactive']],
            ['refund_items', ['quantity_refunded' => 0]],
            ['refund_items', ['quantity_refunded' => 6]],
        ];
        foreach ($cases as [$table, $values]) {
            $before = $this->row($table);
            $this->db->table($table)->where('id', 1)->update($values);
            $record = $service->item(1, 1);
            $this->assertNotNull(Quarantine::releaseProblem($record));
            try { $service->resolve(1, 1, $this->quarantineInput(), Quarantine::revision($record)); $this->fail('Unsafe release accepted: ' . $table); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
            $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
            $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
            $this->db->table($table)->where('id', 1)->update($before);
        }
        // Unknown provenance can still be removed from the pending inspection list without stock movement.
        $this->db->table('sale_items')->where('id', 1)->update(['stock_delivery_id' => null]);
        $this->assertTrue($service->resolve(1, 1, $this->quarantineInput('damaged'), Quarantine::revision($service->item(1, 1))));
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
    }

    public function testQuarantineStaleFormAndInvalidInputCannotMoveStock(): void
    {
        $service = $this->quarantineFixture();
        $revision = Quarantine::revision($service->item(1, 1));
        $this->db->table('branch_products')->where('id', 1)->update(['stock' => 9]);
        try { $service->resolve(1, 1, $this->quarantineInput(), $revision); $this->fail('Stale stock accepted'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('changed', $e->getMessage()); }
        $revision = Quarantine::revision($service->item(1, 1));
        foreach ([['outcome' => 'resellable'], ['outcome' => []], ['note' => '  '], ['note' => str_repeat('a', 501)], ['confirmed' => null], ['note' => "bad\0note"]] as $bad) {
            try { $service->resolve(1, 1, array_replace($this->quarantineInput(), $bad), $revision); $this->fail('Invalid inspection accepted'); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(9, (int) $this->row('branch_products')['stock']);
        $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
    }

    public function testQuarantineAuditFailureRollsBackStockAndOutcome(): void
    {
        $service = $this->quarantineFixture();
        $this->db->query("CREATE TRIGGER fail_inspection_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try { $service->resolve(1, 1, $this->quarantineInput(), Quarantine::revision($service->item(1, 1))); $this->fail('Audit error ignored'); }
        catch (\Throwable $e) { $this->assertStringContainsString('activity_logs', $e->getMessage()); }
        $this->assertSame(10, (int) $this->row('branch_products')['stock']);
        $this->assertSame(10, (int) $this->row('products')['stock']);
        $this->assertSame(0, $this->db->table('stock_logs')->countAllResults());
        $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
    }

    public function testQuarantineOnlyAdministratorsResolveAndInactiveUsersCannotRead(): void
    {
        $service = $this->quarantineFixture();
        $this->db->table('users')->where('id', 1)->update(['role' => 'cashier']);
        $this->assertCount(1, $service->records(1));
        try { $service->resolve(1, 1, $this->quarantineInput(), ''); $this->fail('Cashier resolution accepted'); } catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
        foreach ([['role' => 'cashier', 'status' => 'inactive'], ['role' => 'admin', 'status' => 'inactive']] as $values) {
            $this->db->table('users')->where('id', 1)->update($values);
            try { $service->records(1); $this->fail('Unauthorized list'); } catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
            try { $service->resolve(1, 1, $this->quarantineInput(), ''); $this->fail('Unauthorized resolution'); } catch (\DomainException $e) { $this->assertStringContainsString('administrator', $e->getMessage()); }
        }
        session()->set('role', 'cashier');
        $this->assertSame(403, $this->controller(QuarantineController::class, [])->save(1)->getStatusCode());
        $this->assertSame(403, $this->controller(QuarantineController::class, [])->index()->getStatusCode());
    }

    public function testQuarantineControllerRequiresReviewAndRetainsSavedOutcomeOnRetry(): void
    {
        $service = $this->quarantineFixture();
        $response = $this->controller(QuarantineController::class, $this->quarantineInput())->save(1);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(0, $this->db->table('quarantine_resolutions')->countAllResults());
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(QuarantineController::class, [])->show(1);
        $this->assertStringContainsString('Inspect quarantined return', $html);
        $drafts = session('quarantine_drafts');
        $token = array_key_last($drafts);
        $post = $this->quarantineInput() + ['inspection_token' => $token];
        $this->assertSame(302, $this->controller(QuarantineController::class, $post)->save(1)->getStatusCode());
        $this->assertSame(302, $this->controller(QuarantineController::class, $post)->save(1)->getStatusCode());
        $this->assertSame(1, $this->db->table('stock_logs')->countAllResults());
        \Config\Services::resetSingle('renderer');
        $html = $this->controller(QuarantineController::class, [])->show(1);
        $this->assertStringContainsString('Recorded outcome', $html);
        $this->assertStringNotContainsString('name="outcome"', $html);
    }

    public function testQuarantineMigrationRepeatAndPageFixtures(): void
    {
        $service = $this->quarantineFixture();
        (new \App\Database\Migrations\AddQuarantineResolutions(new \CodeIgniter\Database\SQLite3\Forge($this->db)))->up();
        $this->assertSame(1, $this->db->table('refund_items')->countAllResults());
        $render = function ($action, ...$arguments) {
            \Config\Services::resetSingle('renderer');
            $controller = $this->controller(QuarantineController::class, []);
            service('request')->setGlobal('get', []);
            return $controller->$action(...$arguments);
        };
        $detail = $render('show', 1);
        $this->assertStringContainsString('Release to stock', $detail);
        $this->assertStringContainsString('name="' . csrf_token() . '"', $detail);
        $this->db->table('sale_items')->where('id', 1)->update(['stock_delivery_id' => null]);
        $blocked = $render('show', 1);
        $this->assertStringContainsString('Release to stock is unavailable', $blocked);
        $this->db->table('sale_items')->where('id', 1)->update(['stock_delivery_id' => str_repeat('a', 32)]);
        for ($i = 2; $i <= 32; $i++) { $row = $this->row('refund_items'); $row['id'] = $i; $row['product_name_snapshot'] = $i === 32 ? 'Distinctive quarantine item' : 'Sample held product ' . $i; $this->db->table('refund_items')->insert($row); }
        $list = $render('index');
        $this->assertStringContainsString('js-datatable', $list);
        $service->resolve(1, 1, $this->quarantineInput(), Quarantine::revision($service->item(1, 1)));
        $resolved = $render('show', 1);
        $this->db->table('refund_items')->where('id >', 1)->delete();
        $empty = $render('index');
        $this->db->query('DROP TABLE quarantine_resolutions');
        $this->db->resetDataCache();
        $setup = $render('index');
        $this->assertStringContainsString('Database update required', $setup);
        if (getenv('PHX_QUARANTINE_FIXTURES') === '1') {
            $dir = WRITEPATH . 'quarantine-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach (compact('detail', 'blocked', 'list', 'resolved', 'empty', 'setup') as $name => $html) file_put_contents($dir . '/' . $name . '.html', $html);
        }
    }
}
