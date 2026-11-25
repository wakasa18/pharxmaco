<?php

trait CashierLoginCases
{
    private function cashierLoginSetup(): \App\Libraries\CashierLoginSession
    {
        helper(['url', 'form']);
        $this->cashierSetup(false);
        \Config\Services::injectMock('cache', new \CodeIgniter\Test\Mock\MockCache(new \Config\Cache()));
        $this->db->table('users')->where('id', 2)->update(['username' => 'cashier-two', 'password' => password_hash('demo-test-password', PASSWORD_BCRYPT, ['cost' => 4])]);
        return new \App\Libraries\CashierLoginSession($this->db);
    }

    private function cashierUser(int $id = 2): array
    {
        return $this->db->table('users')->where('id', $id)->get()->getRowArray();
    }

    private function replaceLoginState(array $state = []): void
    {
        session()->remove(array_keys(session()->get()));
        if ($state) session()->set($state);
    }

    private function signInCashier()
    {
        return $this->controller(\App\Controllers\Auth::class, ['username' => 'cashier-two', 'password' => 'demo-test-password'])->attemptLogin();
    }

    public function testCashierLoginBlocksSecondSessionAndPreservesTheFirstCart(): void
    {
        $leases = $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->assertStringContainsString('cashier/start-shift', $this->signInCashier()->getHeaderLine('Location'));
        $this->assertTrue(session('isLoggedIn'));
        $token = session('cashier_login_token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        session()->set('cart', [1 => ['product_id' => 1, 'quantity' => 2]]);
        $first = session()->get();
        $row = $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray();
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($row));

        $this->replaceLoginState();
        $this->assertSame(302, $this->signInCashier()->getStatusCode());
        $this->assertStringContainsString('already signed in', session()->getFlashdata('error'));
        $this->assertEmpty(session('isLoggedIn'));
        $this->assertSame(hash('sha256', $token), $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray()['token_hash']);
        $this->assertArrayNotHasKey('password', session('_ci_old_input')['post']);
        $audit = $this->db->table('activity_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertStringContainsString('already in use', $audit['activity']);
        $this->assertStringNotContainsString('demo-test-password', $audit['activity']);
        $this->assertStringNotContainsString($token, $audit['activity']);
        $this->replaceLoginState($first);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertSame($first['cart'], session('cart'));
        $this->assertTrue($leases->check($this->cashierUser(), $token));
    }

    public function testCashierLoginDifferentAccountsRemainIndependentAndRepeatedChecksWork(): void
    {
        $leases = $this->cashierLoginSetup();
        $one = $leases->claim($this->cashierUser());
        $two = $leases->claim($this->cashierUser(3));
        $this->assertNotNull($one);
        $this->assertNotNull($two);
        $this->assertNull($leases->claim($this->cashierUser()));
        $this->assertTrue($leases->check($this->cashierUser(), $one));
        $this->assertTrue($leases->check($this->cashierUser(), $one));
        $this->assertTrue($leases->check($this->cashierUser(3), $two));
        $this->assertFalse($leases->check($this->cashierUser(3), $one));
        $this->assertSame(2, $this->db->table('cashier_login_sessions')->countAllResults());
    }

    public function testCashierLoginSameBrowserAndSessionRegenerationKeepOwnership(): void
    {
        $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        $token = session('cashier_login_token');
        session()->set('cart', ['keep-this-cart']);
        session()->regenerate(true);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertSame($token, session('cashier_login_token'));
        $this->signInCashier();
        $this->assertSame($token, session('cashier_login_token'));
        $this->assertSame(['keep-this-cart'], session('cart'));
        $this->assertSame(1, $this->db->table('cashier_login_sessions')->countAllResults());
    }

    public function testCashierLoginLogoutReleasesImmediatelyAndStaleLogoutCannotReleaseNewLogin(): void
    {
        $leases = $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        $old = session()->get();
        $this->controller(\App\Controllers\Auth::class, [])->logout();
        $this->assertSame(0, $this->db->table('cashier_login_sessions')->countAllResults());
        $this->assertEmpty(session('isLoggedIn'));
        $this->signInCashier();
        $newToken = session('cashier_login_token');
        $this->assertNotSame($old['cashier_login_token'], $newToken);
        $this->replaceLoginState($old);
        $this->controller(\App\Controllers\Auth::class, [])->logout();
        $this->assertTrue($leases->check($this->cashierUser(), $newToken));
    }

    public function testCashierLoginExpirationAllowsReentryAndOldTokensCannotReviveOrReleaseIt(): void
    {
        $leases = $this->cashierLoginSetup();
        $user = $this->cashierUser();
        $now = time();
        $old = $leases->claim($user, $now);
        $this->assertTrue($leases->check($user, $old, false, $now + 899));
        $this->assertFalse($leases->check($user, $old, true, $now + 900));
        $new = $leases->claim($user, $now + 900);
        $this->assertNotNull($new);
        $this->assertNotSame($old, $new);
        $this->assertFalse($leases->check($user, $old, true, $now + 901));
        $leases->release(2, $old);
        $this->assertTrue($leases->check($user, $new, false, $now + 901));
    }

    public function testCashierLoginBackgroundPollingDoesNotExtendButActivityDoes(): void
    {
        $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        $previous = time() - 100;
        $this->db->table('cashier_login_sessions')->where('user_id', 2)->update(['last_seen_at' => $previous, 'expires_at' => time() + 60]);
        $controller = $this->controller(\App\Controllers\Auth::class, []);
        service('request')->setMethod('GET');
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $row = $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray();
        $this->assertSame($previous, (int) $row['last_seen_at']);
        service('request')->removeHeader('X-Requested-With');
        service('request')->setHeader('Accept', 'application/json');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertSame($previous, (int) $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray()['last_seen_at']);
        $this->assertSame(403, $controller->activity()->getStatusCode());
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        service('request')->setHeader('X-Pharxmaco-Activity', '1');
        $response = $controller->activity();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $row = $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray();
        $this->assertGreaterThan($previous, (int) $row['last_seen_at']);
        $this->assertGreaterThanOrEqual(time() + 898, (int) $row['expires_at']);
    }

    public function testCashierLoginExpiredAndReplacedSessionsReceiveUnauthorizedJson(): void
    {
        $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        session()->set('cart', ['expired-draft']);
        $this->db->table('cashier_login_sessions')->where('user_id', 2)->update(['expires_at' => time() - 1]);
        $this->controller(\App\Controllers\Auth::class, []);
        service('request')->setMethod('GET');
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $response = (new \App\Filters\CashierFilter())->before(service('request'));
        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('expired', $data['message']);
        $this->assertSame(site_url('login'), $data['redirect_url']);
        $this->assertEmpty(session('isLoggedIn'));
        $this->assertEmpty(session('cart'));
    }

    public function testCashierLoginLegacySessionClaimsOnceAndDoesNotEraseTheWinningCart(): void
    {
        $this->cashierLoginSetup();
        $user = $this->cashierUser();
        $legacy = ['isLoggedIn' => true, 'user_id' => 2, 'role' => 'cashier', 'cart' => ['legacy-draft'],
            'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($user)];
        $this->replaceLoginState($legacy);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $winning = session()->get();
        $this->assertSame(['legacy-draft'], session('cart'));
        $this->assertNotEmpty(session('cashier_login_token'));
        $this->replaceLoginState($legacy);
        $this->assertFalse(\App\Libraries\AccountSession::check());
        $this->replaceLoginState($winning);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertSame(['legacy-draft'], session('cart'));
    }

    public function testCashierLoginAccountChangesRejectStaleClaimsAndAllowFreshCredentials(): void
    {
        $leases = $this->cashierLoginSetup();
        $oldUser = $this->cashierUser();
        $oldToken = $leases->claim($oldUser);
        (new \App\Models\UserModel())->update(2, ['password' => 'changed-password-hash']);
        $fresh = $this->cashierUser();
        $freshToken = $leases->claim($fresh);
        $this->assertNotNull($freshToken);
        $this->assertFalse($leases->check($fresh, $oldToken));
        try {
            $leases->claim($oldUser);
            $this->fail('A stale verified account replaced a current login.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('account changed', $error->getMessage());
        }
        $this->assertTrue($leases->check($fresh, $freshToken));
    }

    public function testCashierLoginAdministratorCanEndLoginWithoutClosingShiftOrChangingPassword(): void
    {
        $leases = $this->cashierLoginSetup();
        $service = new \App\Libraries\CashierShift($this->db);
        $shiftId = $service->open(2, 1, '100', str_repeat('c', 32));
        $user = $this->cashierUser();
        $token = $leases->claim($user);
        $this->assertSame(302, $this->controller(\App\Controllers\Admin\Users::class, [])->endLogin(2)->getStatusCode());
        $this->assertSame(0, $this->db->table('cashier_login_sessions')->where('user_id', 2)->countAllResults());
        $this->assertSame('open', $this->db->table('cashier_shifts')->where('id', $shiftId)->get()->getRowArray()['status']);
        $this->assertSame($user['password'], $this->cashierUser()['password']);
        $this->assertNotSame($user['session_version'], $this->cashierUser()['session_version']);
        $this->assertFalse($leases->check($user, $token));
        $this->assertNotNull($leases->claim($this->cashierUser()));
        $audit = $this->db->table('activity_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertStringContainsString('Ended cashier login', $audit['activity']);
        $this->assertStringContainsString('shift left open', $audit['activity']);
    }

    public function testCashierLoginEndLoginIsPostOnlyAdminOnlyAndRollsBackWhenAuditFails(): void
    {
        $leases = $this->cashierLoginSetup();
        $user = $this->cashierUser();
        $token = $leases->claim($user);
        $controller = $this->controller(\App\Controllers\Admin\Users::class, []);
        service('request')->setMethod('GET');
        $this->assertSame(405, $controller->endLogin(2)->getStatusCode());
        $this->replaceLoginState(['isLoggedIn' => true, 'user_id' => 2, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($user), 'cashier_login_token' => $token]);
        $this->assertSame(403, $this->controller(\App\Controllers\Admin\Users::class, [])->endLogin(2)->getStatusCode());
        $this->assertTrue($leases->check($user, $token));
        $admin = $this->row('users');
        $this->replaceLoginState(['isLoggedIn' => true, 'user_id' => 1, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($admin)]);
        $this->db->query("CREATE TRIGGER fail_login_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->controller(\App\Controllers\Admin\Users::class, [])->endLogin(2);
        $this->assertStringContainsString('No account changes', session()->getFlashdata('error'));
        $this->assertSame($user['session_version'], $this->cashierUser()['session_version']);
        $this->assertTrue($leases->check($user, $token));
    }

    public function testCashierLoginMigrationMissingSchemaBlocksCashiersAndLeavesAdminAccessAvailable(): void
    {
        $leases = $this->cashierLoginSetup();
        $user = $this->cashierUser();
        $token = $leases->claim($user);
        (new \App\Database\Migrations\AddCashierLoginSessions(new \CodeIgniter\Database\SQLite3\Forge($this->db)))->up();
        $this->assertTrue($leases->check($user, $token));
        $this->db->query('DROP TABLE cashier_login_sessions');
        $this->assertTrue(\App\Libraries\AccountSession::check(), 'Administrator sessions do not require cashier login storage.');
        $this->replaceLoginState();
        $this->signInCashier();
        $this->assertStringContainsString('000015', session()->getFlashdata('error'));
        $this->assertEmpty(session('isLoggedIn'));
        $this->db->table('users')->where('id', 1)->update(['password' => password_hash('admin-test-password', PASSWORD_BCRYPT, ['cost' => 4])]);
        $response = $this->controller(\App\Controllers\Auth::class, ['username' => 'staff', 'password' => 'admin-test-password'])->attemptLogin();
        $this->assertStringContainsString('/dashboard', $response->getHeaderLine('Location'));
        $this->assertTrue(session('isLoggedIn'));
        $this->assertEmpty(session('cashier_login_token'));
    }

    public function testCashierLoginAdministratorPageOffersPostOnlyEndLoginForCashiers(): void
    {
        $this->cashierLoginSetup();
        $html = $this->controller(\App\Controllers\Admin\Users::class, [])->index();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[contains(@action,"/users/end-login/")]');
        $this->assertSame(2, $forms->length);
        foreach ($forms as $form) {
            $this->assertSame('post', $form->getAttribute('method'));
            $this->assertStringNotContainsString('/end-login/1', $form->getAttribute('action'));
            $this->assertStringContainsString('shift will remain open', $form->getAttribute('onsubmit'));
            $this->assertSame(1, $xpath->query('.//input[@name="' . csrf_token() . '"]', $form)->length);
        }
        $routes = service('routes');
        $routes->loadRoutes();
        $this->assertStringContainsString('admin/users/end-login/', implode(',', array_keys($routes->getRoutes('POST'))));
        $this->assertStringNotContainsString('admin/users/end-login/', implode(',', array_keys($routes->getRoutes('GET'))));
        $this->assertArrayHasKey('csrf', config('Filters')->globals['before']);
        $this->assertNotContains('admin/users/end-login/*', config('Filters')->globals['before']['csrf']['except']);
        $directory = WRITEPATH . 'cashier-session-review/';
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        file_put_contents($directory . 'users.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html));
        $this->replaceLoginState();
        session()->setFlashdata('error', 'This cashier account is already signed in on another session. Sign out there, wait 15 minutes without activity, or ask an administrator to end the login.');
        file_put_contents($directory . 'login-blocked.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', view('auth/login')));
    }

    public function testAdministratorLoginAllowsIndependentSessionsAndKeepsDashboardAndDraft(): void
    {
        $this->cashierLoginSetup();
        $this->db->table('users')->where('id', 1)->update(['password' => password_hash('admin-test-password', PASSWORD_BCRYPT, ['cost' => 4])]);
        $post = ['username' => 'staff', 'password' => 'admin-test-password'];
        $this->replaceLoginState();
        $response = $this->controller(\App\Controllers\Auth::class, $post)->attemptLogin();
        $this->assertStringContainsString('/dashboard', $response->getHeaderLine('Location'));
        $this->assertSame('admin', session('role'));
        $this->assertEmpty(session('cashier_login_token'));
        session()->set('cart', ['administrator-draft']);
        $first = session()->get();
        $this->replaceLoginState();
        $secondResponse = $this->controller(\App\Controllers\Auth::class, $post)->attemptLogin();
        $this->assertStringContainsString('/dashboard', $secondResponse->getHeaderLine('Location'));
        $this->assertTrue(session('isLoggedIn'));
        $this->assertEmpty(session('cashier_login_token'));
        session()->set('cart', ['second-browser-draft']);
        $second = session()->get();
        $this->replaceLoginState($first);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertStringContainsString('/dashboard', $this->controller(\App\Controllers\Auth::class, $post)->attemptLogin()->getHeaderLine('Location'));
        $this->assertSame(['administrator-draft'], session('cart'));
        $this->assertEmpty(session('cashier_login_token'));
        $this->controller(\App\Controllers\Auth::class, [])->logout();
        $this->assertSame(0, $this->db->table('cashier_login_sessions')->where('user_id', 1)->countAllResults());
        $this->replaceLoginState($second);
        $this->assertTrue(\App\Libraries\AccountSession::check(), 'Signing out the first browser must not end the second.');
        $this->assertSame(['second-browser-draft'], session('cart'));
    }

    public function testAdministratorLoginIsNotAnEndLoginTargetOrLeaseOwner(): void
    {
        $leases = $this->cashierLoginSetup();
        $this->db->table('users')->insert(['id' => 4, 'full_name' => 'Another Administrator', 'username' => 'admin-four',
            'password' => 'test-hash', 'role' => 'admin', 'status' => 'active']);
        $other = $this->cashierUser(4);
        try {
            $leases->claim($other);
            $this->fail('Administrators must not claim cashier login ownership.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('only to cashier', $error->getMessage());
        }
        $controller = $this->controller(\App\Controllers\Admin\Users::class, []);
        $controller->endLogin(1);
        $this->assertStringContainsString('cashier account', session()->getFlashdata('error'));
        $controller->endLogin(4);
        $this->assertStringContainsString('cashier account', session()->getFlashdata('error'));
        $this->assertSame($other['session_version'], $this->cashierUser(4)['session_version']);
        $this->assertSame(0, $this->db->table('cashier_login_sessions')->countAllResults());
        $this->assertSame(0, $this->db->table('activity_logs')->countAllResults());
        $document = new \DOMDocument();
        @$document->loadHTML($controller->index());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[contains(@action,"/users/end-login/4")]')->length);
        $this->assertSame(2, $xpath->query('//form[contains(@action,"/users/end-login/")]')->length);
    }

    public function testAdministratorLoginIgnoresOldExpiredLeaseWithoutLosingCartAndStillChecksAccount(): void
    {
        $this->cashierLoginSetup();
        $admin = $this->row('users');
        $token = str_repeat('a', 64);
        $this->db->table('cashier_login_sessions')->insert(['user_id' => 1, 'token_hash' => hash('sha256', $token),
            'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($admin), 'last_seen_at' => time() - 7200, 'expires_at' => time() - 3600]);
        session()->set(['cashier_login_token' => $token, 'cart' => ['keep-legacy-admin-cart']]);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertEmpty(session('cashier_login_token'));
        $this->assertSame(['keep-legacy-admin-cart'], session('cart'));
        $this->db->query('DROP TABLE cashier_login_sessions');
        $controller = $this->controller(\App\Controllers\Auth::class, []);
        service('request')->setMethod('GET');
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        service('request')->setHeader('X-Pharxmaco-Activity', '1');
        $this->assertSame(200, $controller->activity()->getStatusCode());
        $this->assertTrue(\App\Libraries\AccountSession::check());
        (new \App\Models\UserModel())->update(1, ['password' => 'new-authentication-hash']);
        $this->assertFalse(\App\Libraries\AccountSession::check(), 'Removing one-login policy must retain password-change revocation.');
        $this->assertEmpty(session('isLoggedIn'));
    }

    public function testAdministratorLoginUsesNoticeOnlyScriptWhileCashierKeepsActivityRenewal(): void
    {
        $this->cashierLoginSetup();
        foreach (['admin' => '0', 'cashier' => '1'] as $role => $expected) {
            session()->set('role', $role);
            \Config\Services::resetSingle('renderer');
            $html = view('cashier/sales/history', ['sales' => [], 'dateFrom' => '', 'dateTo' => '', 'rowLimit' => 1000]);
            $document = new \DOMDocument();
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $this->assertSame($expected, $xpath->query('//script[contains(@src,"cashier-session.js")]')->item(0)->getAttribute('data-renew-login'));
        }
    }
}
