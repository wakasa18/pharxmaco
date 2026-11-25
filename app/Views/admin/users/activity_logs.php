<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Activity Logs</h1>
    <p class="muted" style="margin:6px 0 0;">View system activity history and track user actions.</p>
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

<div class="card">
    <div class="card-head">
        <div>
            <h2 style="margin:0;">Log Records</h2>
            <p class="muted" style="margin:6px 0 0;">Search and filter recorded system activities.</p>
        </div>

        <a class="btn secondary" href="<?= site_url('admin/users') ?>">← Back to Users</a>
    </div>

    <div class="card-body" style="padding:14px 20px;border-bottom:1.5px solid var(--line);">
    <!-- FILTER -->
    
    </div>

    <!-- TABLE -->
    <?php if (!empty($tableTruncated)): ?>
        <p class="dt-cap-note" role="status">This list is very long, so only the first <?= number_format(3000) ?> records are loaded here.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" data-record-title="1" aria-label="Activity logs" data-dt-length="25">
            <thead>
                <tr>
                    <th style="width:90px;">ID</th>
                    <th style="min-width:180px;">User</th>
                    <th style="min-width:160px;">Username</th>
                    <th style="min-width:320px;">Activity</th>
                    <th style="min-width:180px;">Log Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= esc($log['id']) ?></td>
                            <td><strong><?= esc($log['full_name'] ?? 'Unknown User') ?></strong></td>
                            <td><?= esc($log['username'] ?? '-') ?></td>
                            <td><?= esc($log['activity']) ?></td>
                            <td><?= esc(\App\Libraries\DisplayDate::dateTime($log['log_time'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="muted" style="text-align:center; padding:20px;">
                            No activity logs found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    </div>

<?= $this->endSection() ?>