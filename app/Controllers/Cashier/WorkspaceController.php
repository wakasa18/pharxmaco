<?php

namespace App\Controllers\Cashier;

use App\Controllers\BaseController;
use App\Libraries\CashierDayReport;
use App\Libraries\CashierShift;
use InvalidArgumentException;

class WorkspaceController extends BaseController
{
    private $db;
    private CashierShift $shifts;
    public function __construct() { $this->db = db_connect(); $this->shifts = new CashierShift($this->db); }
    private function branch(): int { return CashierShift::operatingBranch($this->db); }
    private function ready(): bool { return $this->shifts->enabled(); }
    private function requireAdmin(): void
    {
        if (session('role') !== 'admin') throw new InvalidArgumentException('Administrator access is required.');
    }
    private function dates(): array
    {
        $today = substr(CashierShift::now(), 0, 10);
        $from = $this->request->getGet('from'); $to = $this->request->getGet('to');
        return [is_string($from) && $from !== '' ? $from : $today, is_string($to) && $to !== '' ? $to : (is_string($from) && $from !== '' ? $from : $today)];
    }
    private function drawers(): array
    {
        $builder = $this->db->table('cashier_drawers d')->select('d.*, b.branch_name, s.user_id AS current_user_id, u.full_name AS current_cashier, last.cash_left AS handover_cash, last.handover_status, next.full_name AS handover_name, last.handover_to_user_id')
            ->join('branches b', 'b.id = d.branch_id')->join('cashier_shifts s', 's.id = d.active_shift_id', 'left')
            ->join('users u', 'u.id = s.user_id', 'left')->join('cashier_shifts last', 'last.id = d.last_shift_id', 'left')
            ->join('users next', 'next.id = last.handover_to_user_id', 'left')->where('b.status', 'active');
        if (session('role') !== 'admin') $builder->where('d.branch_id', (int) session('branch_id'));
        return $builder->orderBy('b.branch_name')->get()->getResultArray();
    }
    public function start()
    {
        if (!$this->ready()) return view('cashier/workspace/unavailable');
        $active = $this->shifts->activeFor((int) session('user_id'), $this->branch());
        if ($active) return redirect()->to(site_url('cashier/sales'));
        return view('cashier/workspace/start', ['drawers' => $this->drawers(), 'openKey' => bin2hex(random_bytes(16)),
            'branch' => $this->db->table('branches')->where('id', (int) session('branch_id'))->get()->getRowArray()]);
    }
    public function index()
    {
        if (!$this->ready()) return view('cashier/workspace/unavailable');
        $user = (int) session('user_id'); $branch = $this->branch();
        $active = $this->shifts->activeFor($user, $branch);
        if ($active) $branch = (int) $active['branch_id'];
        $this->shifts->importSessionHeld($user, $branch);
        [$from, $to] = $this->dates();
        try { $report = (new CashierDayReport($this->db))->forUser($user, $branch, $from, $to); }
        catch (InvalidArgumentException $e) { return redirect()->to(site_url('cashier/my-day'))->with('error', $e->getMessage()); }
        $alerts = $this->db->table('branch_products bp')->select('bp.id, bp.stock, bp.reorder_level, bp.expiration_date, p.product_name')
            ->join('products p', 'p.id = bp.product_id')->where('bp.branch_id', $branch)
            ->where('bp.status', 'active')->where('bp.is_deleted', 0)->where('bp.is_permanently_deleted', 0)->where('bp.deleted_at', null)
            ->where('p.status', 'active')->where('p.is_deleted', 0)->where('p.is_permanently_deleted', 0)->where('p.deleted_at', null)
            ->groupStart()->where('bp.stock <= bp.reorder_level', null, false)->orGroupStart()->where('bp.expiration_date IS NOT NULL', null, false)
                ->where('bp.expiration_date <=', date('Y-m-d', strtotime(substr(CashierShift::now(), 0, 10) . ' +30 days')))->where('bp.stock >', 0)->groupEnd()->groupEnd()
            ->orderBy('bp.expiration_date')->limit(15)->get()->getResultArray();
        $recent = $this->db->table('cashier_shifts s')->select('s.*, d.drawer_name, b.branch_name, r.reviewed_at')
            ->join('cashier_drawers d', 'd.id = s.drawer_id')->join('branches b', 'b.id = s.branch_id')
            ->join('cashier_shift_reviews r', 'r.shift_id = s.id', 'left')->where('s.user_id', $user);
        if (session('role') !== 'admin') $recent->where('s.branch_id', $branch);
        return view('cashier/workspace/index', ['report' => $report, 'activeShift' => $active,
            'shiftSummary' => $active ? $this->shifts->summary($active) : null, 'drawers' => $this->drawers(),
            'heldSales' => $this->shifts->held($user, $branch), 'alerts' => $alerts,
            'recentShifts' => $recent->orderBy('s.id', 'DESC')->limit(20)->get()->getResultArray(),
            'branch' => $this->db->table('branches')->where('id', $branch)->get()->getRowArray(),
            'openKey' => bin2hex(random_bytes(16)), 'movementKey' => bin2hex(random_bytes(16))]);
    }
    public function summary()
    {
        if (!$this->ready()) return $this->response->setStatusCode(409)->setJSON(['error' => 'Cashier shift upgrade is required.']);
        [$from, $to] = $this->dates();
        try {
            $branch = $this->branch(); $active = $this->shifts->activeFor((int) session('user_id'), $branch);
            return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['report' => (new CashierDayReport($this->db))->forUser((int) session('user_id'), $branch, $from, $to),
                'active_shift_id' => $active['id'] ?? null, 'expected_cash' => $active ? $this->shifts->summary($active)['expected_cash'] : null]);
        } catch (InvalidArgumentException $e) { return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage()]); }
    }
    public function open()
    {
        return $this->save(function () {
            $id = $this->shifts->open((int) session('user_id'), (int) $this->request->getPost('drawer_id'), $this->request->getPost('opening_cash'),
                (string) $this->request->getPost('open_key'), $this->request->getPost('opening_note'));
            $active = $this->shifts->activeFor((int) session('user_id'));
            if (!$active || (int) $active['id'] !== $id) return ['cashier/shifts/' . $id, 'This opening request was already handled. Review the saved shift before starting another.'];
            return ['cashier/sales', 'Shift opened. You can now complete sales.'];
        }, 'cashier/start-shift');
    }
    public function movement()
    {
        return $this->save(function () {
            $this->shifts->cashMovement((int) session('user_id'), $this->branch(), (string) $this->request->getPost('movement_type'),
                $this->request->getPost('amount'), $this->request->getPost('reason'), (string) $this->request->getPost('movement_key'));
            return ['cashier/my-day', 'Cash movement recorded.'];
        });
    }
    private function shiftData(int $id, bool $forPrint = false): array
    {
        $shift = $this->db->table('cashier_shifts s')->select('s.*, u.full_name, b.branch_name, d.drawer_name, next.full_name AS handover_name')
            ->join('users u', 'u.id = s.user_id')->join('branches b', 'b.id = s.branch_id')->join('cashier_drawers d', 'd.id = s.drawer_id')
            ->join('users next', 'next.id = s.handover_to_user_id', 'left')->where('s.id', $id)->get()->getRowArray();
        if (!$shift || (session('role') !== 'admin' && ((int) $shift['user_id'] !== (int) session('user_id') || (int) $shift['branch_id'] !== (int) session('branch_id')))) throw new InvalidArgumentException('Shift not found for your account and branch.');
        $printReport = $forPrint ? $this->shifts->printReport($shift) : null;
        $users = $this->db->table('users')->select('id, full_name')->where('branch_id', $shift['branch_id'])->where('status', 'active')
            ->whereIn('role', ['admin','cashier'])->where('id !=', $shift['user_id'])->orderBy('full_name')->get()->getResultArray();
        return ['shift' => $shift, 'summary' => $printReport['summary'] ?? $this->shifts->summary($shift), 'nextCashiers' => $users,
            'printActivity' => $printReport['activity'] ?? [], 'generatedAt' => CashierShift::now(),
            'heldSales' => $this->shifts->held((int) $shift['user_id'], (int) $shift['branch_id']),
            'review' => $this->db->table('cashier_shift_reviews r')->select('r.*, u.full_name')->join('users u', 'u.id = r.reviewed_by')->where('r.shift_id', $id)->get()->getRowArray(),
            'actions' => $this->db->table('cashier_shift_actions a')->select('a.*, u.full_name')->join('users u', 'u.id = a.user_id')->where('a.shift_id', $id)->orderBy('a.id')->get()->getResultArray()];
    }
    public function shift(int $id)
    {
        if (!$this->ready()) return view('cashier/workspace/unavailable');
        try { return view('cashier/workspace/shift', $this->shiftData($id)); }
        catch (InvalidArgumentException $e) { return $this->response->setStatusCode(404)->setBody(esc($e->getMessage())); }
    }
    public function printShift(int $id)
    {
        if (!$this->ready()) return view('cashier/workspace/unavailable');
        try { return view('cashier/workspace/print', $this->shiftData($id, true)); }
        catch (InvalidArgumentException $e) { return $this->response->setStatusCode(404)->setBody(esc($e->getMessage())); }
    }
    public function close(int $id)
    {
        return $this->save(function () use ($id) {
            $data = $this->shiftData($id);
            if ((int) $data['shift']['user_id'] === (int) session('user_id') && !empty(session('cart'))) throw new InvalidArgumentException('Complete, hold or clear your active POS cart before closing.');
            $this->shifts->close($id, (int) session('user_id'), $this->request->getPost('counted_cash'), $this->request->getPost('cash_left'),
                $this->request->getPost('closing_note'), (string) $this->request->getPost('shift_revision'),
                (int) $this->request->getPost('handover_to_user_id') ?: null, $this->request->getPost('discard_held') === '1');
            return ['cashier/shifts/' . $id, 'Shift closed. The counted totals have been saved for review.'];
        }, 'cashier/shifts/' . $id);
    }
    public function manager()
    {
        $this->requireAdmin(); if (!$this->ready()) return view('cashier/workspace/unavailable');
        $beforeShift = max(0, (int) $this->request->getGet('before_shift'));
        $beforeEvent = max(0, (int) $this->request->getGet('before_event'));
        $onlyPending = $this->request->getGet('pending') === '1';
        $shiftQuery = $this->db->table('cashier_shifts s')->select('s.*, u.full_name, b.branch_name, r.reviewed_at')
            ->join('users u', 'u.id = s.user_id')->join('branches b', 'b.id = s.branch_id')->join('cashier_shift_reviews r', 'r.shift_id = s.id', 'left')
            ->orderBy('s.id', 'DESC');
        if ($beforeShift) $shiftQuery->where('s.id <', $beforeShift);
        if ($onlyPending) $shiftQuery->where('s.status', 'closed')->where('r.id', null);
        $shifts = $shiftQuery->limit(200)->get()->getResultArray();
        $eventQuery = $this->db->table('cashier_events e')->select('e.*, u.full_name, b.branch_name')->join('users u', 'u.id = e.user_id')
            ->join('branches b', 'b.id = e.branch_id')->join('cashier_event_reviews r', 'r.event_id = e.id', 'left')
            ->whereIn('e.event_type', ['refund','exchange','adjustment','cash_in','cash_out'])->where('r.id', null)
            ->notLike('e.event_key', 'baseline:', 'after')->orderBy('e.id', 'DESC');
        if ($beforeEvent) $eventQuery->where('e.id <', $beforeEvent);
        $exceptions = $eventQuery->limit(200)->get()->getResultArray();
        return view('cashier/workspace/manager', ['shifts' => $shifts, 'exceptions' => $exceptions, 'drawers' => $this->drawers(),
            'onlyPending' => $onlyPending, 'beforeShift' => $beforeShift, 'beforeEvent' => $beforeEvent,
            'branches' => $this->db->table('branches')->where('status', 'active')->orderBy('branch_name')->get()->getResultArray()]);
    }
    public function review(string $kind, int $id)
    {
        return $this->save(function () use ($kind, $id) {
            $this->requireAdmin(); $this->shifts->review((int) session('user_id'), $kind, $id, $this->request->getPost('review_note'));
            return [$kind === 'shift' ? 'cashier/shifts/' . $id : 'admin/cashier-shifts', 'Review recorded.'];
        }, 'admin/cashier-shifts');
    }
    public function release(int $id)
    {
        return $this->save(function () use ($id) {
            $this->requireAdmin(); $this->shifts->releaseHandover((int) session('user_id'), $id, $this->request->getPost('reason'));
            return ['cashier/shifts/' . $id, 'Handover reservation released. The next cashier must count the opening cash.'];
        }, 'cashier/shifts/' . $id);
    }
    public function configureDrawer()
    {
        return $this->save(function () {
            $this->requireAdmin();
            $branch = (int) $this->request->getPost('branch_id');
            if (!$this->db->table('branches')->where('id', $branch)->where('status', 'active')->countAllResults()) throw new InvalidArgumentException('Select an active branch.');
            $this->db->transBegin();
            try {
                $this->db->query('SELECT id FROM branches WHERE id = ?' . ($this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE'), [$branch]);
                $drawer = $this->db->table('cashier_drawers')->where('branch_id', $branch)->get()->getRowArray();
                if (!$drawer) $this->db->table('cashier_drawers')->insert(['branch_id' => $branch, 'drawer_name' => 'Main drawer', 'status' => 'active', 'created_at' => CashierShift::now()]);
                elseif ($this->request->getPost('drawer_status') === 'inactive' && empty($drawer['active_shift_id'])) $this->db->table('cashier_drawers')->where('id', $drawer['id'])->update(['status' => 'inactive']);
                elseif ($this->request->getPost('drawer_status') === 'active') $this->db->table('cashier_drawers')->where('id', $drawer['id'])->update(['status' => 'active']);
                else throw new InvalidArgumentException('Close the active shift before disabling this drawer.');
                $this->shifts->audit((int) session('user_id'), 'Updated shared drawer availability for branch #' . $branch);
                if (!$this->db->transStatus()) throw new \RuntimeException('Drawer update failed.');
                $this->db->transCommit();
            } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
            return ['admin/cashier-shifts', 'Shared branch drawer updated.'];
        }, 'admin/cashier-shifts');
    }
    private function save(callable $work, string $back = 'cashier/my-day')
    {
        try {
            if (!$this->ready()) throw new InvalidArgumentException('Apply the cashier shift SQL upgrade first.');
            [$path, $message] = $work(); return redirect()->to(site_url($path))->with('success', $message);
        } catch (InvalidArgumentException $e) { return redirect()->to(site_url($back))->withInput()->with('error', $e->getMessage()); }
        catch (\Throwable $e) { log_message('error', 'Cashier workflow failed: {message}', ['message' => $e->getMessage()]); return redirect()->to(site_url($back))->withInput()->with('error', 'The update could not be saved. Refresh and check the latest status before trying again.'); }
    }
}
