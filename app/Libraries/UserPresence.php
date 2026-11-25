<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** Presence capabilities cannot sign in, renew login leases or access financial data. */
final class UserPresence
{
    public const ONLINE_SECONDS = 120;
    private const TABLE = 'user_presence_sessions';

    public function __construct(private BaseConnection $db)
    {
    }

    public function enabled(): bool
    {
        return $this->db->tableExists(self::TABLE);
    }

    public static function attachCurrent(array $user, bool $refresh = true): void
    {
        try {
            $service = new self(db_connect());
            if (!$service->enabled()) return;
            $session = session();
            $token = $session->get('presence_token');
            $now = time();
            $fingerprint = AccountSession::fingerprint($user);
            $row = is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)
                ? $service->db->table(self::TABLE)->where('token_hash', hash('sha256', $token))->get()->getRowArray() : null;
            if ($row && (int) $row['user_id'] === (int) $user['id'] && $row['ended_at'] === null
                && (int) $row['expires_at'] > $now && hash_equals($fingerprint, $row['account_fingerprint'])) {
                if ($refresh) {
                    $service->db->table(self::TABLE)->where('token_hash', $row['token_hash'])->where('ended_at', null)
                        ->update(['last_seen_at' => $now, 'expires_at' => $now + self::lifetime()]);
                }
                return;
            }
            if (!$refresh) return;
            // A new capability is issued only by a verified account request, never by a heartbeat.
            $token = bin2hex(random_bytes(32));
            $cashierToken = (string) $session->get('cashier_login_token');
            if ($service->db->table(self::TABLE)->insert([
                'token_hash' => hash('sha256', $token), 'user_id' => (int) $user['id'],
                'account_fingerprint' => $fingerprint,
                'cashier_token_hash' => $user['role'] === 'cashier' ? hash('sha256', $cashierToken) : null,
                'created_at' => $now, 'last_seen_at' => $now, 'expires_at' => $now + self::lifetime(), 'ended_at' => null,
            ])) $session->set('presence_token', $token);
        } catch (\Throwable $error) {
            // Tracking is optional and must not block authentication or a sale.
            log_message('error', 'User presence registration unavailable.');
        }
    }

    private static function lifetime(): int
    {
        $seconds = config(\Config\Session::class)->expiration;
        return $seconds > 0 ? $seconds : 7200;
    }

    public static function endCurrent(): void
    {
        try {
            $token = session('presence_token');
            if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return;
            $service = new self(db_connect());
            if ($service->enabled()) $service->db->table(self::TABLE)->where('token_hash', hash('sha256', $token))
                ->where('user_id', (int) session('user_id'))->where('ended_at', null)->update(['ended_at' => time()]);
        } catch (\Throwable $error) {
            log_message('error', 'User presence release unavailable.');
        }
    }

    private function valid(array $row, array $user, int $now, ?array $lease): bool
    {
        if ($row['ended_at'] !== null || (int) $row['expires_at'] <= $now || $user['status'] !== 'active'
            || !in_array($user['role'], ['admin', 'cashier'], true)
            || !hash_equals(AccountSession::fingerprint($user), $row['account_fingerprint'])) return false;
        return $user['role'] === 'admin' || ($lease && (int) $lease['expires_at'] > $now
            && hash_equals((string) $lease['token_hash'], (string) $row['cashier_token_hash'])
            && hash_equals($row['account_fingerprint'], $lease['account_fingerprint']));
    }

    public function authorize(string $token, ?int $now = null): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !$this->enabled()) return null;
        $now ??= time();
        $row = $this->db->table(self::TABLE)->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        if (!$row) return null;
        $user = $this->db->table('users')->where('id', (int) $row['user_id'])->get()->getRowArray();
        if (!$user) return null;
        $lease = $user['role'] === 'cashier' && $this->db->tableExists('cashier_login_sessions')
            ? $this->db->table('cashier_login_sessions')->where('user_id', (int) $user['id'])->get()->getRowArray() : null;
        return $this->valid($row, $user, $now, $lease) ? ['user' => $user, 'presence' => $row] : null;
    }

    public function heartbeat(string $token, ?int $now = null): bool
    {
        $now ??= time();
        $identity = $this->authorize($token, $now);
        if (!$identity) return false;
        $owned = fn () => $this->db->table(self::TABLE)->where('token_hash', $identity['presence']['token_hash'])
            ->where('ended_at', null)->where('expires_at >', $now);
        if (!$owned()->update(['last_seen_at' => $now])) return false;
        // Same-second requests can report zero changed rows in MySQL.
        return $owned()->countAllResults() === 1;
    }

    public function summary(?int $now = null): array
    {
        $now ??= time();
        if (!$this->enabled()) return ['available' => false, 'users' => [], 'online_count' => null];
        $users = [];
        foreach ($this->db->table('users')->get()->getResultArray() as $user) $users[(int) $user['id']] = $user;
        $leases = [];
        if ($this->db->tableExists('cashier_login_sessions')) {
            foreach ($this->db->table('cashier_login_sessions')->get()->getResultArray() as $lease) $leases[(int) $lease['user_id']] = $lease;
        }
        $result = [];
        foreach ($users as $id => $user) $result[$id] = ['online' => false, 'last_seen_at' => null, 'last_seen_label' => 'Never'];
        foreach ($this->db->table(self::TABLE)->select('user_id, MAX(last_seen_at) AS last_seen_at')->groupBy('user_id')->get()->getResultArray() as $row) {
            $id = (int) $row['user_id'];
            if (!isset($users[$id])) continue;
            $seen = (int) $row['last_seen_at'];
            if ($seen > 0) {
                $result[$id]['last_seen_at'] = $seen;
                $result[$id]['last_seen_label'] = DisplayDate::dateTime($seen);
            }
        }
        foreach ($this->db->table(self::TABLE)->where('last_seen_at >', $now - self::ONLINE_SECONDS)
            ->where('expires_at >', $now)->where('ended_at', null)->get()->getResultArray() as $row) {
            $id = (int) $row['user_id'];
            if (isset($users[$id]) && $this->valid($row, $users[$id], $now, $leases[$id] ?? null)) $result[$id]['online'] = true;
        }
        return ['available' => true, 'users' => $result, 'online_count' => count(array_filter($result, static fn ($item) => $item['online'])),
            'as_of' => $now, 'as_of_label' => DisplayDate::dateTime($now)];
    }
}
