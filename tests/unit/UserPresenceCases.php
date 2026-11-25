<?php

trait UserPresenceCases
{
    private function presenceSetup(): \App\Libraries\UserPresence
    {
        $this->cashierLoginSetup();
        session()->remove('presence_token');
        require_once APPPATH . 'Database/Migrations/2026_10_21_000016_add_user_presence_sessions.php';
        (new \App\Database\Migrations\AddUserPresenceSessions(new \CodeIgniter\Database\SQLite3\Forge($this->db)))->up();
        return new \App\Libraries\UserPresence($this->db);
    }

    private function presenceRow(string $token): array
    {
        return $this->db->table('user_presence_sessions')->where('token_hash', hash('sha256', $token))->get()->getRowArray();
    }

    public function testUserPresenceSupportsMultipleAdminSessionsAndLogoutOnlyEndsOne(): void
    {
        $service = $this->presenceSetup();
        $user = $this->row('users');
        \App\Libraries\UserPresence::attachCurrent($user);
        $one = session()->get();
        $this->assertTrue($service->summary()['users'][1]['online']);
        $this->replaceLoginState(['isLoggedIn' => true, 'user_id' => 1, 'account_fingerprint' => \App\Libraries\AccountSession::fingerprint($user)]);
        \App\Libraries\UserPresence::attachCurrent($user);
        $two = session('presence_token');
        $this->assertNotSame($one['presence_token'], $two);
        $this->assertSame(2, $this->db->table('user_presence_sessions')->countAllResults());
        $this->assertSame(1, $service->summary()['online_count']);
        $this->replaceLoginState($one);
        $this->controller(\App\Controllers\Auth::class, [])->logout();
        $this->assertNotNull($this->presenceRow($one['presence_token'])['ended_at']);
        $this->assertFalse($service->heartbeat($one['presence_token']));
        $this->assertTrue($service->summary()['users'][1]['online']);
        $this->assertTrue($service->heartbeat($two));
        $this->assertSame(0, $this->db->table('cashier_login_sessions')->countAllResults());
    }

    public function testUserPresenceTimesOutAtTwoMinutesAndRetainsLastSeenAfterLogout(): void
    {
        $service = $this->presenceSetup();
        \App\Libraries\UserPresence::attachCurrent($this->row('users'));
        $token = session('presence_token');
        $seen = $this->presenceRow($token)['last_seen_at'];
        $this->assertTrue($service->summary($seen + 119)['users'][1]['online']);
        $this->assertFalse($service->summary($seen + 120)['users'][1]['online']);
        $this->assertTrue($service->heartbeat($token, $seen + 150));
        $this->assertTrue($service->summary($seen + 150)['users'][1]['online']);
        $this->assertFalse($service->summary()['users'][2]['online']);
        $this->assertSame('Never', $service->summary()['users'][2]['last_seen_label']);
        \App\Libraries\UserPresence::endCurrent();
        $summary = $service->summary($seen + 150);
        $this->assertFalse($summary['users'][1]['online']);
        $this->assertSame($seen + 150, $summary['users'][1]['last_seen_at']);
    }

    public function testUserPresenceHeartbeatCannotExtendCashierLeaseOrPresenceAuthorization(): void
    {
        $service = $this->presenceSetup();
        $this->replaceLoginState();
        $this->signInCashier();
        $token = session('presence_token');
        $row = $this->presenceRow($token);
        $lease = $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray();
        $this->assertTrue($service->heartbeat($token, (int) $row['last_seen_at'] + 100));
        $this->assertSame($row['expires_at'], $this->presenceRow($token)['expires_at']);
        $this->assertSame($lease, $this->db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray());
        $this->assertFalse($service->heartbeat($token, (int) $lease['expires_at']));
        $this->assertFalse($service->summary((int) $lease['expires_at'])['users'][2]['online']);
        $this->assertNull($service->authorize($token, (int) $row['expires_at']));
    }

    public function testUserPresenceAdminRenewalComesOnlyFromVerifiedRealRequests(): void
    {
        $service = $this->presenceSetup();
        \App\Libraries\UserPresence::attachCurrent($this->row('users'));
        $token = session('presence_token');
        $deadline = time() + 60;
        $this->db->table('user_presence_sessions')->where('token_hash', hash('sha256', $token))->update(['expires_at' => $deadline]);
        $this->assertTrue($service->heartbeat($token));
        $this->assertSame($deadline, (int) $this->presenceRow($token)['expires_at']);
        $this->controller(\App\Controllers\Auth::class, []);
        service('request')->setMethod('GET');
        service('request')->setHeader('Accept', 'application/json');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertSame($deadline, (int) $this->presenceRow($token)['expires_at']);
        service('request')->removeHeader('Accept');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertGreaterThan($deadline, (int) $this->presenceRow($token)['expires_at']);
    }

    public function testUserPresenceRejectsChangedAccountsAndCashierEndLoginImmediately(): void
    {
        $service = $this->presenceSetup();
        $adminState = session()->get();
        $this->replaceLoginState();
        $this->signInCashier();
        $token = session('presence_token');
        $this->replaceLoginState($adminState);
        $this->controller(\App\Controllers\Admin\Users::class, [])->endLogin(2);
        $this->assertNull($service->authorize($token));
        $this->assertFalse($service->summary()['users'][2]['online']);
        \App\Libraries\UserPresence::attachCurrent($this->row('users'));
        $adminToken = session('presence_token');
        (new \App\Models\UserModel())->update(1, ['password' => 'changed-hash']);
        $this->assertNull($service->authorize($adminToken));
        $this->assertFalse($service->summary()['users'][1]['online']);
    }

    public function testUserPresenceStoresHashedTokensAndScopedApiRejectsAnonymousAndCashierReads(): void
    {
        $service = $this->presenceSetup();
        \App\Libraries\UserPresence::attachCurrent($this->row('users'));
        $token = session('presence_token');
        $this->assertStringNotContainsString($token, json_encode($this->presenceRow($token)));
        $this->assertNull($service->authorize(str_repeat('f', 64)));
        $this->replaceLoginState(); // The capability works without sending a PHP session cookie.
        $controller = $this->controller(\App\Controllers\Presence::class, []);
        service('request')->setMethod('GET');
        $this->assertSame(401, $controller->users()->getStatusCode());
        service('request')->setHeader('X-Pharxmaco-Presence', $token);
        $response = $controller->users();
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getBody(), true);
        $this->assertTrue($json['users'][1]['online']);
        $this->assertStringNotContainsString('password', $response->getBody());
        $this->assertStringNotContainsString('fingerprint', $response->getBody());
        $this->assertEmpty(session('isLoggedIn'));
        $this->signInCashier();
        $cashierToken = session('presence_token');
        $controller = $this->controller(\App\Controllers\Presence::class, []);
        service('request')->setMethod('GET');
        service('request')->setHeader('X-Pharxmaco-Presence', $cashierToken);
        $this->assertSame(403, $controller->users()->getStatusCode());
        service('request')->setMethod('POST');
        $this->assertSame(200, $controller->heartbeat()->getStatusCode());
        $this->assertSame(405, $controller->users()->getStatusCode());
        service('request')->setMethod('GET');
        $this->assertSame(405, $controller->heartbeat()->getStatusCode());
    }

    public function testUserPresenceRolloutPreservesDraftAndMissingSchemaDoesNotBlockLogin(): void
    {
        $service = $this->presenceSetup();
        session()->set('cart', ['existing-draft']);
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->assertNotEmpty(session('presence_token'));
        $this->assertSame(['existing-draft'], session('cart'));
        $before = $this->db->table('user_presence_sessions')->get()->getResultArray();
        (new \App\Database\Migrations\AddUserPresenceSessions(new \CodeIgniter\Database\SQLite3\Forge($this->db)))->up();
        $this->assertSame($before, $this->db->table('user_presence_sessions')->get()->getResultArray());
        $this->db->query('DROP TABLE user_presence_sessions');
        $this->assertTrue(\App\Libraries\AccountSession::check());
        $this->replaceLoginState();
        $this->signInCashier();
        $this->assertTrue(session('isLoggedIn'));
        $this->assertEmpty(session('presence_token'));
    }

    public function testUserPresenceViewSeparatesAccountStatusAndRetainsPostActions(): void
    {
        $this->presenceSetup();
        \App\Libraries\UserPresence::attachCurrent($this->row('users'));
        \Config\Services::resetSingle('renderer'); // Each page request starts with fresh view sections.
        $controller = $this->controller(\App\Controllers\Admin\Users::class, []);
        service('request')->setGlobal('get', []);
        $html = $controller->index();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//th[text()="Account status"]')->length);
        $this->assertSame(1, $xpath->query('//th[text()="Presence"]')->length);
        $this->assertSame(1, $xpath->query('//th[text()="Last seen"]')->length);
        $this->assertSame('Online', trim($xpath->query('//tr[@data-user-id="1"]//td[@data-presence-status]')->item(0)->textContent));
        $this->assertSame('Never', trim($xpath->query('//tr[@data-user-id="2"]//td[@data-presence-last-seen]')->item(0)->textContent));
        $this->assertSame(2, $xpath->query('//form[contains(@action,"end-login")]')->length);
    }
}
