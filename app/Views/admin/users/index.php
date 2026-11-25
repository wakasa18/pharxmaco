<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<?php $presence = $presence ?? ['available' => false, 'users' => [], 'online_count' => null]; ?>

<style>
    [data-presence-status] .badge-secondary { background:var(--surface-2); color:var(--muted); border-color:var(--line-2); }
    /* ===== CARDS ===== */
    .compact-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
    }

    .card.mini {
        padding: 14px 12px;
        border-radius: 14px;
    }

    .card.mini h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .card.mini p {
        margin: 4px 0 0;
        font-size: 12px;
    }

</style>

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">User Management</h1>
    <p class="muted" style="margin:6px 0 0;">View and manage all system users and their branch assignments.</p>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert success">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert danger">
        <?= esc(session()->getFlashdata('error')) ?>
    </div>
    <div class="sp"></div>
<?php endif; ?>

<!-- CARDS -->
<div class="cards compact-cards" style="margin-bottom:20px;">
    <div class="card mini">
        <h4><?= esc($totalUsers) ?></h4>
        <p class="muted">Total</p>
    </div>

    <div class="card mini">
        <h4><?= esc($activeUsers) ?></h4>
        <p class="muted">Active</p>
    </div>

    <div class="card mini">
        <h4><?= esc($inactiveUsers) ?></h4>
        <p class="muted">Inactive</p>
    </div>

    <div class="card mini">
        <h4><?= esc($adminUsers) ?></h4>
        <p class="muted">Admins</p>
    </div>

    <div class="card mini">
        <h4><?= esc($cashierUsers) ?></h4>
        <p class="muted">Cashiers</p>
    </div>
    <div class="card mini">
        <h4 data-online-count><?= $presence['available'] ? (int) $presence['online_count'] : '—' ?></h4>
        <p class="muted">Online now</p>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">User List</h2>
            <p class="muted" style="margin:6px 0 0;">Manage accounts, roles, status, and branch assignment.</p>
        </div>

        <div class="row" style="gap:10px;">
            <a class="btn secondary" href="<?= site_url('admin/activity-logs') ?>">Activity Logs</a>
            <a class="btn btn-primary" href="<?= site_url('admin/users/create') ?>">+ Add User</a>
        </div>
    </div>

    <div class="card-body" style="padding:14px 20px;border-bottom:1.5px solid var(--line);">
        <p class="muted" style="margin:0;font-size:12px;line-height:1.6;">Online means at least one session checked in during the last two minutes. Account status shows whether the account is enabled. Last seen uses Philippine time.</p>
        <p class="muted" data-presence-update style="margin:4px 0 0;font-size:12px;" role="status"><?= $presence['available'] ? 'Updates automatically every 30 seconds.' : 'Online status is currently unavailable.' ?></p>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" data-record-title="1" data-presence-users-url="<?= esc(site_url('presence/users'), 'attr') ?>" aria-label="Users">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Account status</th>
                    <th data-dt-filter="off">Presence</th>
                    <th style="min-width:160px;">Created At</th>
                    <th data-dt-filter="off" style="min-width:160px;">Last seen</th>
                    <th style="min-width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userBranch = $user['branch_name'] ?? null;

                        if (($user['role'] ?? '') === 'admin') {
                            $userBranchDisplay = 'All Branches';
                        } else {
                            $userBranchDisplay = !empty($userBranch) ? $userBranch : 'Unassigned';
                        }
                        ?>
                        <?php $seen = $presence['users'][(int) $user['id']] ?? ['online' => false, 'last_seen_at' => null, 'last_seen_label' => 'Never']; ?>
                        <tr data-user-id="<?= (int) $user['id'] ?>">
                            <td><?= esc($user['id']) ?></td>
                            <td><strong><?= esc($user['full_name']) ?></strong></td>
                            <td><?= esc($user['username']) ?></td>
                            <td><?= esc(ucfirst($user['role'])) ?></td>
                            <td><?= esc($userBranchDisplay) ?></td>

                            <td>
                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td data-presence-status data-order="<?= $presence['available'] ? ($seen['online'] ? 1 : 0) : -1 ?>">
                                <span class="badge <?= !$presence['available'] ? 'badge-warning' : ($seen['online'] ? 'badge-success' : 'badge-secondary') ?>"><?= !$presence['available'] ? 'Unavailable' : ($seen['online'] ? 'Online' : 'Offline') ?></span>
                            </td>
                            <td><?= !empty($user['created_at']) ? esc(\App\Libraries\DisplayDate::dateTime($user['created_at'])) : '-' ?></td>
                            <td data-presence-last-seen data-order="<?= (int) ($seen['last_seen_at'] ?? -1) * 1000 ?>"><?= esc($presence['available'] ? $seen['last_seen_label'] : '—') ?></td>

                            <td>
                                <div class="row" style="gap:6px; flex-wrap:wrap;">
                                    <a href="<?= site_url('admin/users/edit/' . $user['id']) ?>"
                                        class="btn secondary btn-sm">Edit</a>

                                    <a href="<?= site_url('admin/users/password/' . $user['id']) ?>"
                                        class="btn btn-sm">Password</a>

                                    <?php if (($user['role'] ?? '') === 'cashier' && (int) $user['id'] !== (int) session('user_id')): ?>
                                        <form method="post" action="<?= site_url('admin/users/end-login/' . (int) $user['id']) ?>" class="inline-action-form"
                                            onsubmit="return confirm('End this account login? Save or hold any active cart first. Any shift will remain open.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn secondary btn-sm" data-busy-label="Ending login…">End login</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                        <form method="post" action="<?= site_url('admin/users/deactivate/' . $user['id']) ?>" class="inline-action-form"
                                            onsubmit="return confirm('Deactivate this user account?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn danger btn-sm" data-busy-label="Deactivating…">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= site_url('admin/users/activate/' . $user['id']) ?>" class="inline-action-form"
                                            onsubmit="return confirm('Activate this user account?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm" data-busy-label="Activating…">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="muted" style="text-align:center; padding:20px;">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    </div>

<?= $this->endSection() ?>
