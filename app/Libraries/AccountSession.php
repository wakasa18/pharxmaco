<?php

namespace App\Libraries;

use App\Models\UserModel;

final class AccountSession
{
    public static function fingerprint(array $user): string
    {
        return hash('sha256', json_encode([
            (int) $user['id'], $user['password'], $user['role'],
            (int) ($user['branch_id'] ?? 0), $user['status'],
            $user['session_version'] ?? '',
        ], JSON_THROW_ON_ERROR));
    }

    public static function check(bool $touch = true): bool
    {
        $session = session();
        if (!$session->get('isLoggedIn')) {
            return false;
        }
        $user = (new UserModel())->find((int) $session->get('user_id'));
        $valid = $user && $user['status'] === 'active'
            && in_array($user['role'], ['admin', 'cashier'], true)
            && hash_equals(self::fingerprint($user), (string) $session->get('account_fingerprint'));
        $loginMessage = null;
        if ($valid && $user['role'] === 'cashier') {
            try {
                $leases = new CashierLoginSession(db_connect());
                $token = $session->get('cashier_login_token');
                // On rollout, the first valid legacy session claims the account without losing its cart.
                if (!is_string($token) || $token === '') {
                    $token = $leases->claim($user);
                    if ($token) $session->set('cashier_login_token', $token);
                }
                $request = service('request');
                // Background GET polling does not count as account activity.
                $background = $request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')
                    || $request->getHeaderLine('Sec-Fetch-Dest') === 'empty';
                $renew = $touch && (!$background || !$request->is('get'));
                $valid = is_string($token) && $leases->check($user, $token, $renew);
                if (!$valid) $loginMessage = 'Your login expired or was ended. Please sign in again.';
            } catch (\Throwable $error) {
                $valid = false;
                $loginMessage = 'Account login protection is unavailable. Check database update 000015 before signing in.';
            }
        }
        if ($valid && $user['role'] === 'admin') {
            // Older administrator sessions may carry a lease from the previous policy.
            // Ignore it without discarding their cart or depending on the cashier table.
            $session->remove('cashier_login_token');
        }
        if (!$valid) {
            UserPresence::endCurrent();
            CashierLoginSession::releaseCurrent();
            // Remove carts and branch-bound state as well as authentication.
            $session->remove(array_keys($session->get()));
            $session->regenerate(true);
            if ($loginMessage) $session->setFlashdata('error', $loginMessage);
            return false;
        }
        $session->set([
            'role' => $user['role'], 'branch_id' => $user['branch_id'],
            'full_name' => $user['full_name'], 'username' => $user['username'],
        ]);
        $request = service('request');
        $background = $request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')
            || $request->getHeaderLine('Sec-Fetch-Dest') === 'empty';
        UserPresence::attachCurrent($user, $touch && (!$background || !$request->is('get')));
        return true;
    }

    public static function rejection()
    {
        $message = session()->getFlashdata('error') ?: 'Please log in first.';
        if (service('request')->isAJAX() || str_contains(service('request')->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setHeader('Cache-Control', 'no-store')->setJSON([
                'success' => false, 'message' => $message, 'redirect_url' => site_url('login'),
            ]);
        }
        return redirect()->to(site_url('login'))->with('error', $message);
    }
}
