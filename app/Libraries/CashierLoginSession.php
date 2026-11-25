<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** One account-bound lease per cashier. Administrators use ordinary independent sessions. */
final class CashierLoginSession
{
    public const IDLE_SECONDS = 900;
    private const TABLE = 'cashier_login_sessions';

    public function __construct(private BaseConnection $db)
    {
    }

    public function enabled(): bool
    {
        return $this->db->tableExists(self::TABLE);
    }

    /** Null means another session owns the account. The raw token never enters the database. */
    public function claim(array $verifiedUser, ?int $now = null): ?string
    {
        if (($verifiedUser['role'] ?? '') !== 'cashier') throw new \InvalidArgumentException('Single-login protection applies only to cashier accounts.');
        if (!$this->enabled()) throw new \RuntimeException('Account login protection needs database update 000015. Apply it before signing in.');
        $now ??= time();
        $fingerprint = AccountSession::fingerprint($verifiedUser);
        $token = bin2hex(random_bytes(32));
        if (!$this->db->transBegin()) throw new \RuntimeException('Could not start cashier login protection.');
        try {
            $lock = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
            $user = $this->db->query('SELECT * FROM users WHERE id = ?' . $lock, [(int) $verifiedUser['id']])->getRowArray();
            if (!$user || $user['status'] !== 'active' || $user['role'] !== 'cashier'
                || !hash_equals(AccountSession::fingerprint($user), $fingerprint)) {
                throw new \RuntimeException('This account changed while signing in. Please sign in again.');
            }
            if (!$this->db->table(self::TABLE)->ignore(true)->insert([
                'user_id' => (int) $user['id'], 'token_hash' => null, 'account_fingerprint' => $fingerprint,
                'last_seen_at' => 0, 'expires_at' => 0,
            ])) throw new \RuntimeException('Could not prepare cashier login protection.');

            $saved = $this->db->table(self::TABLE)->where('user_id', (int) $user['id'])
                ->groupStart()->where('token_hash', null)->orWhere('expires_at <=', $now)
                ->orWhere('account_fingerprint !=', $fingerprint)->groupEnd()
                ->update(['token_hash' => hash('sha256', $token), 'account_fingerprint' => $fingerprint,
                    'last_seen_at' => $now, 'expires_at' => $now + self::IDLE_SECONDS]);
            if (!$saved || !$this->db->transStatus()) throw new \RuntimeException('Could not secure the cashier login.');
            $claimed = $this->db->affectedRows() === 1;
            if (!$this->db->transCommit()) throw new \RuntimeException('Could not secure the cashier login.');
            return $claimed ? $token : null;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    /** An expired or replaced token cannot extend, revive or release a newer login. */
    public function check(array $user, string $token, bool $touch = true, ?int $now = null): bool
    {
        if (($user['role'] ?? '') !== 'cashier') return false;
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !$this->enabled()) return false;
        $now ??= time();
        $owned = fn () => $this->db->table(self::TABLE)->where('user_id', (int) $user['id'])
            ->where('token_hash', hash('sha256', $token))->where('account_fingerprint', AccountSession::fingerprint($user))
            ->where('expires_at >', $now);
        if ($touch && !$owned()->update(['last_seen_at' => $now, 'expires_at' => $now + self::IDLE_SECONDS])) return false;
        // MySQL can report zero changed rows for two checks in the same second.
        return $owned()->countAllResults() === 1;
    }

    public function release(int $userId, string $token): void
    {
        if ($userId <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $token) || !$this->enabled()) return;
        if (!$this->db->table(self::TABLE)->where('user_id', $userId)->where('token_hash', hash('sha256', $token))->delete()) {
            throw new \RuntimeException('Could not release the cashier login.');
        }
    }

    public static function releaseCurrent(): void
    {
        try {
            (new self(db_connect()))->release((int) session('user_id'), (string) session('cashier_login_token'));
        } catch (\Throwable $error) {
            // Signing out locally still succeeds; the lease expires without further activity.
            log_message('error', 'Cashier login release could not be saved; account_id={id}', ['id' => (int) session('user_id')]);
        }
    }
}
