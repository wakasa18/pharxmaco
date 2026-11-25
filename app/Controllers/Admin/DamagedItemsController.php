<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DamagedItems;
use App\Libraries\ReturnRecordAccess;

class DamagedItemsController extends BaseController
{
    private function staff(): bool
    {
        return session('isLoggedIn') && in_array(session('role'), ['admin', 'cashier'], true);
    }

    public function index()
    {
        if (!$this->staff()) return $this->response->setStatusCode(403)->setBody('Staff access required.');
        $source = $this->request->getGet('source') ?? 'all';
        $branch = $this->request->getGet('branch_id') ?? '0';
        if (!is_string($source) || ($source !== 'all' && !isset(DamagedItems::SOURCES[$source]))
            || !is_string($branch) || !ctype_digit($branch) || (int) $branch > 2147483647) {
            return $this->response->setStatusCode(400)->setBody('Invalid damaged-items filter.');
        }
        $service = new DamagedItems(db_connect());
        try {
            $scope = ReturnRecordAccess::scope(db_connect(), (int) session('user_id'), (int) $branch);
            $branch = $scope['branch_id'];
            $canViewAllBranches = $scope['can_view_all_branches'];
            $records = $service->records((int) session('user_id'), $source, $branch);
        }
        catch (\DomainException $e) { return $this->response->setStatusCode(403)->setBody(esc($e->getMessage())); }
        $includesInspections = $service->includesInspections();
        $branchQuery = db_connect()->table('branches');
        if (!$canViewAllBranches) $branchQuery->where('id', $branch);
        $branches = $branchQuery->orderBy('branch_name')->get()->getResultArray();
        $totalQuantity = array_sum(array_column($records, 'damaged_quantity'));
        return view('admin/damaged_items/index', compact('records', 'source', 'branch', 'branches', 'includesInspections', 'totalQuantity', 'canViewAllBranches'));
    }

    public function show(int $id)
    {
        if (!$this->staff()) return $this->response->setStatusCode(403)->setBody('Staff access required.');
        try { $record = (new DamagedItems(db_connect()))->item($id, (int) session('user_id')); }
        catch (\DomainException $e) { return $this->response->setStatusCode(403)->setBody(esc($e->getMessage())); }
        catch (\InvalidArgumentException $e) { return $this->response->setStatusCode(404)->setBody(esc($e->getMessage())); }
        $slipParams = [];
        if (preg_match('/^[a-f0-9]{32}$/D', (string) $record['refund_event_id'])) $slipParams['event'] = $record['refund_event_id'];
        elseif (!empty($record['created_at'])) $slipParams['ts'] = $record['created_at'];
        $slipUrl = $slipParams ? site_url('cashier/sales/refund-slip/' . (int) $record['sale_id']) . '?' . http_build_query($slipParams) : null;
        return view('admin/damaged_items/show', compact('record', 'slipUrl'));
    }
}
