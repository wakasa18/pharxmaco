<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Quarantine;
use App\Libraries\ReturnRecordAccess;

class QuarantineController extends BaseController
{
    private function admin(): bool
    {
        return session('isLoggedIn') && session('role') === 'admin';
    }

    private function staff(): bool
    {
        return session('isLoggedIn') && in_array(session('role'), ['admin', 'cashier'], true);
    }

    public function index()
    {
        if (!$this->staff()) return $this->response->setStatusCode(403)->setBody('Staff access required.');
        $service = new Quarantine(db_connect());
        $ready = $service->ready();
        $status = $this->request->getGet('status') ?? 'pending';
        $branch = $this->request->getGet('branch_id') ?? '0';
        if (!is_string($status) || !in_array($status, array_merge(['pending', 'all'], array_keys(Quarantine::OUTCOMES)), true)
            || !is_scalar($branch) || !ctype_digit((string) $branch) || (int) $branch > 2147483647) {
            return $this->response->setStatusCode(400)->setBody('Invalid quarantine filter.');
        }
        try {
            $scope = ReturnRecordAccess::scope(db_connect(), (int) session('user_id'), (int) $branch);
            $branch = $scope['branch_id'];
            $canInspect = $scope['can_inspect'];
            $canViewAllBranches = $scope['can_view_all_branches'];
            $records = $ready ? $service->records((int) session('user_id'), $status, $branch) : [];
        } catch (\DomainException $e) { return $this->response->setStatusCode(403)->setBody(esc($e->getMessage())); }
        $branchQuery = db_connect()->table('branches');
        if (!$canViewAllBranches) $branchQuery->where('id', $branch);
        $branches = $branchQuery->orderBy('branch_name')->get()->getResultArray();
        return view('admin/quarantine/index', compact('records', 'status', 'branch', 'branches', 'ready', 'canInspect', 'canViewAllBranches'));
    }

    public function show(int $id)
    {
        return $this->render($id);
    }

    private function render(int $id, array $input = [], ?string $error = null)
    {
        if (!$this->staff()) return $this->response->setStatusCode(403)->setBody('Staff access required.');
        $service = new Quarantine(db_connect());
        if (!$service->ready()) return redirect()->to(site_url('admin/quarantine'))->with('error', 'Import database update 000018 before inspecting returns.');
        try {
            $scope = ReturnRecordAccess::scope(db_connect(), (int) session('user_id'));
            $canInspect = $scope['can_inspect'];
            $record = $service->item($id, (int) session('user_id'));
        }
        catch (\InvalidArgumentException $e) { return redirect()->to(site_url('admin/quarantine'))->with('error', $e->getMessage()); }
        catch (\DomainException $e) { return $this->response->setStatusCode(403)->setBody(esc($e->getMessage())); }
        $token = '';
        if ($canInspect && !$record['resolution']) {
            $token = bin2hex(random_bytes(24));
            $drafts = session('quarantine_drafts') ?? [];
            $drafts = is_array($drafts) ? array_filter($drafts, static fn ($draft) => ($draft['time'] ?? 0) >= time() - 7200) : [];
            $drafts[$token] = ['id' => $id, 'revision' => Quarantine::revision($record), 'time' => time()];
            session()->set('quarantine_drafts', array_slice($drafts, -20, null, true));
        }
        $releaseProblem = Quarantine::releaseProblem($record);
        return view('admin/quarantine/show', compact('record', 'token', 'releaseProblem', 'input', 'error', 'canInspect'));
    }

    public function save(int $id)
    {
        if (!$this->admin()) return $this->response->setStatusCode(403)->setBody('Administrator access required.');
        if (!$this->request->is('post')) return $this->response->setStatusCode(405);
        $token = $this->request->getPost('inspection_token');
        $drafts = session('quarantine_drafts');
        $draft = is_string($token) && preg_match('/^[a-f0-9]{48}$/D', $token) && is_array($drafts) ? ($drafts[$token] ?? null) : null;
        if (!$draft || $draft['id'] !== $id || $draft['time'] < time() - 7200) {
            return redirect()->to(site_url('admin/quarantine/' . $id))->with('error', 'This inspection form expired. Review the return again before saving.');
        }
        try {
            $service = new Quarantine(db_connect());
            if (!$service->ready()) return redirect()->to(site_url('admin/quarantine'))->with('error', 'Import database update 000018 before inspecting returns.');
            $saved = $service->resolve($id, (int) session('user_id'), $this->request->getPost(), $draft['revision']);
        } catch (\InvalidArgumentException $e) { return $this->render($id, $this->request->getPost(), $e->getMessage()); }
        catch (\DomainException $e) { return $this->response->setStatusCode(403)->setBody(esc($e->getMessage())); }
        catch (\Throwable $e) {
            log_message('error', 'Quarantine inspection failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('admin/quarantine/' . $id))->with('error', 'The inspection could not be confirmed. Reopen this return to check its recorded outcome before retrying.');
        }
        return redirect()->to(site_url('admin/quarantine/' . $id))->with('success', $saved ? 'Inspection recorded.' : 'This return was already inspected. No additional stock was added.');
    }
}
