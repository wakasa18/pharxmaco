<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/login-history.css') ?>?v=20261021">

<div class="page-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">My Login History</h1>
    <p class="muted" style="margin:6px 0 0;">Review recorded sign-ins, login attempts, and sign-outs for your account.</p>
</div>

<section class="card login-history">
    <div class="card-head">
        <div><h2 style="margin:0;">Login activity</h2><p class="muted" style="margin:6px 0 0;">Times are shown in Philippine time. If you do not recognize a login, contact an administrator.</p></div>
    </div>
    <div class="login-history-filters">
        <form id="login-history-range" method="get" action="<?= site_url('cashier/login-history') ?>" data-auto-filter="off" aria-label="Filter login history by date" aria-describedby="login-history-note<?= $dateRangeError !== '' ? ' login-history-error' : '' ?>">
            <label for="login-date-from"><span>From date</span><input id="login-date-from" class="input" type="date" name="date_from" value="<?= esc($dateFrom, 'attr') ?>"></label>
            <label for="login-date-to"><span>To date</span><input id="login-date-to" class="input" type="date" name="date_to" value="<?= esc($dateTo, 'attr') ?>"></label>
            <div class="login-history-actions"><button type="submit" class="btn btn-primary">Apply</button><a href="<?= site_url('cashier/login-history') ?>" class="btn btn-secondary">Clear</a></div>
        </form>
        <?php if ($dateRangeError !== ''): ?><p id="login-history-error" class="login-history-error" role="alert"><?= esc($dateRangeError) ?></p><?php endif; ?>
        <p id="login-history-note" class="login-history-note" role="status">
            <?php if ($dateRangeError !== ''): ?>No records loaded. Correct the date range to continue.
            <?php elseif ($tableTruncated): ?>Showing the latest <?= number_format($rowLimit) ?> records<?= ($dateFrom !== '' || $dateTo !== '') ? ' in this date range' : '' ?>. Narrow the date range to find older records.
            <?php else: ?>Showing <?= number_format(count($records)) ?> recorded <?= count($records) === 1 ? 'event' : 'events' ?><?= ($dateFrom !== '' || $dateTo !== '') ? ' in this date range' : '' ?>.
            <?php endif; ?>Quick search filters loaded records only. Only authentication events linked to your account are included.
        </p>
    </div>
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" aria-label="My login history" aria-describedby="login-history-note" data-record-title="1" data-dt-order="0:desc" data-dt-length="25" data-dt-date-filter="off" data-dt-link-ignore="date_from,date_to" data-dt-empty="No login history found for this date range.">
            <thead><tr><th data-dt-filter="off">Date and time</th><th>Result</th><th data-dt-filter="off">IP address</th></tr></thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td data-label="Date and time" data-order="<?= esc($record['log_time'], 'attr') ?>"><?= esc(\App\Libraries\DisplayDate::dateTime($record['log_time'])) ?></td>
                        <td data-label="Result"><?php if ($record['event'] === 'success'): ?><span class="badge badge-success"><?= esc($record['label']) ?></span><?php elseif ($record['event'] === 'logout'): ?><span class="badge"><?= esc($record['label']) ?></span><?php else: ?><span class="badge badge-warning"><?= esc($record['label']) ?></span><?php endif; ?></td>
                        <td data-label="IP address" class="login-history-ip"><?= esc($record['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?><tr><td colspan="3" class="muted" style="text-align:center;padding:20px;">No login history found for this date range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
