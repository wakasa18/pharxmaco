<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;

class AuthenticationAudit
{
    private const LABELS = [
        'success' => 'Login succeeded',
        'credentials' => 'Login failed: invalid credentials',
        'validation' => 'Login failed: required fields missing',
        'inactive' => 'Login denied: inactive account',
        'role' => 'Login denied: invalid role',
        'locked' => 'Login blocked: rate limit active',
        'lockout' => 'Login failed: rate limit reached',
        'logout' => 'Logout succeeded',
        'session_busy' => 'Login denied: account already in use',
        'session_setup' => 'Login denied: account session protection unavailable',
    ];

    /** Read only the structured authentication messages produced by record(). */
    public static function details(string $activity): ?array
    {
        if (!str_starts_with($activity, 'Authentication: ')) return null;
        $parts = explode('; IP: ', substr($activity, strlen('Authentication: ')), 2);
        if (count($parts) !== 2) return null;
        $event = array_search($parts[0], self::LABELS, true);
        if ($event === false) return null;
        return ['event' => $event, 'label' => self::LABELS[$event],
            'ip' => filter_var($parts[1], FILTER_VALIDATE_IP) ? $parts[1] : 'Unavailable'];
    }

    /** Never accept request bodies, usernames, passwords, or session tokens here. */
    public static function record(string $event, ?int $userId, string $ip): void
    {
        if (!isset(self::LABELS[$event])) throw new \InvalidArgumentException('Unknown authentication event.');
        $address = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unavailable';
        $activity = 'Authentication: ' . self::LABELS[$event] . '; IP: ' . $address;
        try {
            if (!(new ActivityLogModel())->insert([
                'user_id' => $userId && $userId > 0 ? $userId : null,
                'activity' => $activity, 'log_time' => date('Y-m-d H:i:s'),
            ])) throw new \RuntimeException('Audit insert failed.');
        } catch (\Throwable $exception) {
            log_message('error', 'Authentication audit fallback: {activity}; account_id={account}', [
                'activity' => $activity, 'account' => $userId ?? 'unknown',
            ]);
        }
    }
}
