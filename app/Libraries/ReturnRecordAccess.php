<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** Return-history access follows the current database account, never a requested cashier branch. */
final class ReturnRecordAccess
{
    public static function scope(BaseConnection $db, int $userId, int $requestedBranch = 0): array
    {
        $user = $db->table('users')->where('id', $userId)->get()->getRowArray();
        if (!$user || $user['status'] !== 'active' || !in_array($user['role'], ['admin', 'cashier'], true)) {
            throw new \DomainException('Only an active administrator or cashier can view return records.');
        }
        $admin = $user['role'] === 'admin';
        $branch = $admin ? $requestedBranch : (int) ($user['branch_id'] ?? 0);
        if (!$admin && ($branch < 1 || !$db->table('branches')->where('id', $branch)->countAllResults())) {
            throw new \DomainException('A valid assigned branch is required to view return records.');
        }
        return ['branch_id' => $branch, 'can_inspect' => $admin, 'can_view_all_branches' => $admin];
    }
}
