<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Libraries\AuthenticationAudit;
use App\Libraries\AccountSession;

class Auth extends Controller
{
    // Maximum failed attempts before lockout
    private const MAX_ATTEMPTS = 5;

    // Lockout duration in seconds (15 minutes)
    private const LOCKOUT_SECONDS = 15 * 60;

    public function login()
    {
        // Revalidate the account so revoked sessions cannot bypass sign-in.
        if (AccountSession::check()) {
            return redirect()->to(site_url(session('role') === 'admin' ? 'dashboard' : \App\Libraries\CashierShift::cashierHome(db_connect())));
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        $session = session();
        $db      = \Config\Database::connect();
        $cache   = \Config\Services::cache();

        // ── Throttle check ────────────────────────────────────────────────
        $ip        = $this->request->getIPAddress();
        $lockKey   = 'login_locked_'   . md5($ip);
        $countKey  = 'login_attempts_' . md5($ip);

        $lockedUntil = $cache->get($lockKey);

        if ($lockedUntil !== null && $lockedUntil > time()) {
            AuthenticationAudit::record('locked', null, $ip);
            $minutes = ceil(($lockedUntil - time()) / 60);
            return redirect()
                ->back()
                ->with('error', "Too many failed login attempts. Please try again in {$minutes} minute(s).");
        }
        // ──────────────────────────────────────────────────────────────────

        $rules = [
            'username' => 'required',
            'password' => 'required',
        ];

        if (!is_string($this->request->getPost('username')) || !is_string($this->request->getPost('password')) || !$this->validate($rules)) {
            AuthenticationAudit::record('validation', null, $ip);
            return $this->loginError('Username and password are required.');
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = $db->table('users')
            ->select('users.*, branches.branch_name')
            ->join('branches', 'branches.id = users.branch_id', 'left')
            ->where('users.username', $username)
            ->get()
            ->getRowArray();

        // ── Failed login: record attempt ───────────────────────────────────
        if (!$user || !password_verify($password, $user['password'])) {
            $attempts = (int) ($cache->get($countKey) ?? 0) + 1;
            $cache->save($countKey, $attempts, self::LOCKOUT_SECONDS);

            $remaining = self::MAX_ATTEMPTS - $attempts;

            if ($attempts >= self::MAX_ATTEMPTS) {
                AuthenticationAudit::record('lockout', isset($user['id']) ? (int) $user['id'] : null, $ip);
                $cache->save($lockKey, time() + self::LOCKOUT_SECONDS, self::LOCKOUT_SECONDS);
                $cache->delete($countKey);
                return redirect()
                    ->back()
                    ->with('error', 'Too many failed login attempts. Your IP has been locked out for 15 minutes.');
            }

            $warn = $remaining === 1
                ? ' Warning: 1 attempt remaining before lockout.'
                : " {$remaining} attempts remaining before lockout.";

            AuthenticationAudit::record('credentials', isset($user['id']) ? (int) $user['id'] : null, $ip);
            return $this->loginError('Invalid username or password.' . $warn);
        }
        // ──────────────────────────────────────────────────────────────────

        if (($user['status'] ?? 'active') !== 'active') {
            AuthenticationAudit::record('inactive', (int) $user['id'], $ip);
            return $this->loginError('Your account is inactive. Please contact the administrator.');
        }

        if (!in_array($user['role'], ['admin', 'cashier'], true)) {
            AuthenticationAudit::record('role', (int) $user['id'], $ip);
            return redirect()->to('/login')->with('error', 'Invalid role.');
        }

        $cashierToken = null;
        if ((int) $session->get('user_id') === (int) $user['id']
            && ($user['role'] === 'admin' || $session->get('cashier_login_token')) && AccountSession::check()) {
            return redirect()->to(site_url($user['role'] === 'admin' ? 'dashboard' : \App\Libraries\CashierShift::cashierHome($db)));
        }
        if ($user['role'] === 'cashier') {
            try {
                $cashierToken = (new \App\Libraries\CashierLoginSession($db))->claim($user);
            } catch (\Throwable $error) {
                AuthenticationAudit::record('session_setup', (int) $user['id'], $ip);
                return $this->loginError('Account login protection is unavailable. Check database update 000015 before signing in.');
            }
            if ($cashierToken === null) {
                AuthenticationAudit::record('session_busy', (int) $user['id'], $ip);
                return $this->loginError('This account is already signed in on another session. Sign out there, wait 15 minutes without activity, or ask another administrator to end the login.');
            }
        }

        // ── Successful login: clear throttle counters ─────────────────────
        $cache->delete($lockKey);
        $cache->delete($countKey);
        // ──────────────────────────────────────────────────────────────────

        \App\Libraries\UserPresence::endCurrent();
        \App\Libraries\CashierLoginSession::releaseCurrent();
        $session->remove(array_keys($session->get()));
        $session->regenerate(true);
        $session->set([
            'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($user),
            'user_id'     => $user['id'],
            'full_name'   => $user['full_name'],
            'username'    => $user['username'],
            'role'        => $user['role'],
            'branch_id'   => $user['branch_id']   ?? null,
            'branch_name' => $user['branch_name'] ?? null,
            'isLoggedIn'  => true,
        ]);
        if ($cashierToken !== null) $session->set('cashier_login_token', $cashierToken);
        \App\Libraries\UserPresence::attachCurrent($user);

        AuthenticationAudit::record('success', (int) $user['id'], $ip);
        if ($user['role'] === 'admin') {
            return redirect()->to('/dashboard');
        }

        if ($user['role'] === 'cashier') {
            return redirect()->to(site_url(\App\Libraries\CashierShift::cashierHome($db)));
        }

        return redirect()->to('/login')->with('error', 'Invalid role.');
    }

    public function logout()
    {
        if (!$this->request->is('post')) {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST');
        }
        if (session('isLoggedIn') && session('user_id')) {
            AuthenticationAudit::record('logout', (int) session('user_id'), $this->request->getIPAddress());
        }
        \App\Libraries\UserPresence::endCurrent();
        \App\Libraries\CashierLoginSession::releaseCurrent();
        // Discard account/cart data and the old session ID, then keep only
        // the sign-out message in a fresh anonymous session.
        session()->remove(array_keys(session()->get()));
        session()->regenerate(true);
        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }

    public function activity()
    {
        if (!$this->request->isAJAX() || $this->request->getHeaderLine('X-Pharxmaco-Activity') !== '1') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false]);
        }
        if (!AccountSession::check(false)) return AccountSession::rejection();
        if (session('role') === 'cashier') {
            $user = (new \App\Models\UserModel())->find((int) session('user_id'));
            if (!$user || !(new \App\Libraries\CashierLoginSession(db_connect()))->check($user, (string) session('cashier_login_token'))) {
                return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Your login expired. Please sign in again.', 'redirect_url' => site_url('login')]);
            }
        }
        return $this->response->setStatusCode(200)->setHeader('Cache-Control', 'no-store')->setJSON(['success' => true]);
    }

    private function loginError(string $message)
    {
        // withInput() also stores passwords and CSRF tokens in the session.
        $username = $this->request->getPost('username');
        return redirect()->back()->with('_ci_old_input', [
            'get' => [],
            'post' => ['username' => is_string($username) ? $username : ''],
        ])->with('error', $message);
    }
}
