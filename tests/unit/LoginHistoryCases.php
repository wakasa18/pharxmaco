<?php

trait LoginHistoryCases
{
    private function loginHistorySignIn(): void
    {
        $this->cashierLoginSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        $this->assertTrue(session('isLoggedIn'));
        $this->db->table('activity_logs')->emptyTable();
    }

    private function loginHistoryResponse(array $query = [])
    {
        $controller = $this->controller(\App\Controllers\Cashier\LoginHistoryController::class, []);
        service('request')->setMethod('GET');
        service('request')->setGlobal('get', $query);
        \Config\Services::resetSingle('renderer');
        return $controller->index();
    }

    private function loginHistoryPage(array $query = []): DOMXPath
    {
        $response = $this->loginHistoryResponse($query);
        $this->assertSame(200, $response->getStatusCode());
        $dom = new DOMDocument();
        @$dom->loadHTML($response->getBody());
        return new DOMXPath($dom);
    }

    private function loginHistoryRows(DOMXPath $page): array
    {
        return array_map(static fn ($node) => preg_replace('/\s+/', ' ', trim($node->textContent)), iterator_to_array(
            $page->query('//table[@aria-label="My login history"]/tbody/tr[not(td[@colspan])]')
        ));
    }

    private function loginHistoryEvent(?int $userId, string $event, string $ip, string $date): void
    {
        \App\Libraries\AuthenticationAudit::record($event, $userId, $ip);
        $this->db->table('activity_logs')->where('id', $this->db->insertID())->update(['log_time' => $date]);
    }

    public function testLoginHistoryShowsOnlyCurrentCashierAuthenticationEventsDespiteForgedFilters(): void
    {
        $this->loginHistorySignIn();
        $this->loginHistoryEvent(2, 'success', '192.0.2.2', '2026-10-21 08:00:00');
        $this->loginHistoryEvent(2, 'credentials', '2001:db8::2', '2026-10-20 08:00:00');
        $this->loginHistoryEvent(2, 'logout', '192.0.2.3', '2026-10-19 08:00:00');
        $this->loginHistoryEvent(3, 'success', '198.51.100.3', '2026-10-21 09:00:00');
        $this->loginHistoryEvent(1, 'success', '198.51.100.1', '2026-10-21 09:00:00');
        $this->loginHistoryEvent(null, 'locked', '198.51.100.9', '2026-10-21 09:00:00');
        $this->db->table('activity_logs')->insert(['user_id' => 2, 'activity' => 'Updated unrelated product SECRET-PRODUCT', 'log_time' => '2026-10-21 09:00:00']);
        foreach ([[], ['user_id' => '3', 'id' => '1', 'username' => 'other', 'branch_id' => '2'], ['user_id' => ['1', '3']]] as $query) {
            $rows = $this->loginHistoryRows($this->loginHistoryPage($query));
            $this->assertCount(3, $rows);
            $this->assertStringContainsString('Login succeeded', $rows[0]);
            $this->assertStringContainsString('192.0.2.2', $rows[0]);
            $this->assertStringContainsString('2001:db8::2', $rows[1]);
            $this->assertStringContainsString('Logout succeeded', $rows[2]);
            $this->assertStringNotContainsString('198.51.100', implode(' ', $rows));
            $this->assertStringNotContainsString('SECRET-PRODUCT', implode(' ', $rows));
        }
    }

    public function testLoginHistoryRejectsAnonymousAdminRevokedAndInactiveAccounts(): void
    {
        $this->loginHistorySignIn();
        $cashierState = session()->get();
        $this->replaceLoginState();
        $this->assertSame(302, $this->loginHistoryResponse()->getStatusCode());
        $admin = $this->row('users');
        $this->replaceLoginState(['isLoggedIn' => true, 'user_id' => 1, 'role' => 'admin',
            'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($admin)]);
        $this->assertSame(403, $this->loginHistoryResponse()->getStatusCode());
        $this->replaceLoginState($cashierState);
        $this->db->table('cashier_login_sessions')->where('user_id', 2)->delete();
        $this->assertSame(302, $this->loginHistoryResponse()->getStatusCode());
        $this->replaceLoginState($cashierState);
        $this->db->table('users')->where('id', 2)->update(['status' => 'inactive']);
        $this->assertSame(302, $this->loginHistoryResponse()->getStatusCode());
    }

    public function testLoginHistoryWholeDayRangeAndOpenBounds(): void
    {
        $this->loginHistorySignIn();
        foreach (['2026-10-19 23:59:59', '2026-10-20 00:00:00', '2026-10-20 23:59:59', '2026-10-21 00:00:00'] as $i => $date) {
            $this->loginHistoryEvent(2, 'success', '192.0.2.' . ($i + 1), $date);
        }
        $rows = $this->loginHistoryRows($this->loginHistoryPage(['date_from' => '2026-10-20', 'date_to' => '2026-10-20']));
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('192.0.2.3', $rows[0]);
        $this->assertStringContainsString('192.0.2.2', $rows[1]);
        $this->assertCount(3, $this->loginHistoryRows($this->loginHistoryPage(['date_to' => '2026-10-20'])));
        $this->assertCount(1, $this->loginHistoryRows($this->loginHistoryPage(['date_from' => '2026-10-21'])));
    }

    public function testLoginHistoryInvalidDatesLoadNoRecords(): void
    {
        $this->loginHistorySignIn();
        $this->loginHistoryEvent(2, 'success', '192.0.2.2', '2026-10-20 08:00:00');
        foreach ([['date_from' => '2026-02-30'], ['date_to' => '0000-01-01'], ['date_from' => '2026-1-01'],
            ['date_to' => ['2026-10-20']], ['date_from' => "2026-10-20\0"], ['date_to' => '<script>'],
            ['date_from' => '2026-10-21', 'date_to' => '2026-10-20']] as $query) {
            $page = $this->loginHistoryPage($query);
            $this->assertSame([], $this->loginHistoryRows($page));
            $this->assertSame(1, $page->query('//*[@id="login-history-error" and @role="alert"]')->length);
        }
    }

    public function testLoginHistoryRangeCanReachRecordsOutsideLatestThousand(): void
    {
        $this->loginHistorySignIn();
        $this->loginHistoryEvent(2, 'success', '192.0.2.2', '2025-01-01 08:00:00');
        $batch = [];
        for ($i = 0; $i < 1001; $i++) $batch[] = ['user_id' => 2, 'activity' => 'Authentication: Login succeeded; IP: 192.0.2.3', 'log_time' => '2026-10-20 08:00:00'];
        $this->db->table('activity_logs')->insertBatch($batch);
        $latest = $this->loginHistoryPage();
        $this->assertCount(1000, $this->loginHistoryRows($latest));
        $this->assertStringContainsString('latest 1,000 records', $latest->query('//*[@id="login-history-note"]')->item(0)->textContent);
        $older = $this->loginHistoryRows($this->loginHistoryPage(['date_to' => '2025-01-01']));
        $this->assertCount(1, $older);
        $this->assertStringContainsString('192.0.2.2', $older[0]);
    }

    public function testLoginHistoryReadsExistingAuditWithoutChangingDraftOrCreatingEvents(): void
    {
        $this->loginHistorySignIn();
        $this->loginHistoryEvent(2, 'session_busy', '192.0.2.4', '2026-10-20 08:00:00');
        session()->set(['cart' => ['keep-draft'], 'checkout_token' => 'keep-checkout']);
        $before = $this->db->table('activity_logs')->get()->getResultArray();
        $response = $this->loginHistoryResponse();
        $this->assertSame('private, no-store, max-age=0', $response->getHeaderLine('Cache-Control'));
        $this->assertSame(['keep-draft'], session('cart'));
        $this->assertSame('keep-checkout', session('checkout_token'));
        $this->assertSame($before, $this->db->table('activity_logs')->get()->getResultArray());
        $this->assertSame(0, $this->db->table('cashier_shifts')->countAllResults());
    }

    public function testLoginHistoryParsesOnlyKnownAuthenticationMessagesWithoutSecrets(): void
    {
        $audit = \App\Libraries\AuthenticationAudit::class;
        $this->assertNull($audit::details('Changed user password: secret'));
        $this->assertNull($audit::details('Authentication: arbitrary input; IP: 192.0.2.2'));
        $this->assertNull($audit::details('Authentication: Login succeeded'));
        $this->assertSame('Unavailable', $audit::details('Authentication: Login succeeded; IP: <img src=x onerror=alert(1)>')['ip']);
        foreach (['success', 'credentials', 'validation', 'inactive', 'role', 'locked', 'lockout', 'logout', 'session_busy', 'session_setup'] as $event) {
            $audit::record($event, 1, '2001:db8::1');
            $row = $this->db->table('activity_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame($event, $audit::details($row['activity'])['event']);
            $this->assertSame('2001:db8::1', $audit::details($row['activity'])['ip']);
        }
    }

    public function testLoginHistoryNavigationTableAndEmptyState(): void
    {
        $this->loginHistorySignIn();
        $empty = $this->loginHistoryResponse()->getBody();
        $this->assertStringContainsString('No login history found for this date range.', $empty);
        for ($i = 0; $i < 35; $i++) $this->loginHistoryEvent(2, $i % 3 === 0 ? 'credentials' : ($i % 3 === 1 ? 'success' : 'logout'), $i % 2 === 0 ? '2001:db8:1234:5678:90ab:cdef:1234:5678' : '192.0.2.2', '2026-10-' . str_pad((string) (21 - intdiv($i, 3)), 2, '0', STR_PAD_LEFT) . ' 08:00:00');
        $response = $this->loginHistoryResponse();
        $dom = new DOMDocument(); @$dom->loadHTML($response->getBody()); $page = new DOMXPath($dom);
        $this->assertSame(1, $page->query('//*[@id="accountPanel"]//a[contains(@href,"cashier/login-history")]')->length);
        $this->assertSame(1, $page->query('//*[@id="sidebarNavigation"]//a[contains(@href,"cashier/login-history")]')->length);
        $this->assertSame(2, $page->query('//*[@id="login-history-range"]//input[@type="date"]')->length);
        $this->assertSame(1, $page->query('//table[contains(@class,"js-datatable") and @data-dt-order="0:desc" and @data-dt-date-filter="off"]')->length);
        $routes = service('routes'); $routes->loadRoutes();
        $this->assertStringContainsString('LoginHistoryController::index', $routes->getRoutes('GET')['cashier/login-history']);
        $this->assertSame(['cashier'], $routes->getFiltersForRoute('cashier/login-history', 'GET'));
        if (getenv('PHX_LOGIN_HISTORY_FIXTURES') === '1') {
            $dir = WRITEPATH . 'login-history-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir . '/history.html', $response->getBody());
            file_put_contents($dir . '/empty.html', $empty);
        }
    }
}
