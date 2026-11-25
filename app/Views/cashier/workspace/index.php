<?= $this->extend('layouts/staff') ?><?= $this->section('content') ?>
<?php
use App\Libraries\CashierShift;
use App\Libraries\DisplayDate;
$nowStamp = CashierShift::now(); $today = substr($nowStamp, 0, 10); $hour = (int) substr($nowStamp, 11, 2);
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$peso = static fn($value) => ((float) $value < 0 ? '−' : '') . '₱' . number_format(abs((float) $value), 2);
$day = static fn(string $offset) => date('Y-m-d', strtotime($today . ' ' . $offset));
$ranges = ['Today' => [$today, $today], 'Yesterday' => [$day('-1 day'), $day('-1 day')], 'Last 7 days' => [$day('-6 days'), $today], 'This month' => [substr($today, 0, 8) . '01', $today]];
$rangeLabel = $report['from'] === $report['to'] ? DisplayDate::date($report['from']) : DisplayDate::date($report['from']) . ' – ' . DisplayDate::date($report['to']);
$isCashier = session('role') === 'cashier';
$summaryUrl = site_url('cashier/my-day/summary') . '?from=' . urlencode($report['from']) . '&to=' . urlencode($report['to']);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/cashier-workspace.css') ?>?v=20261023">
<div class="cashier-workspace" data-my-day data-summary-url="<?= esc($summaryUrl, 'attr') ?>" data-active-shift="<?= (int) ($activeShift['id'] ?? 0) ?>" data-sale-url="<?= esc(site_url('cashier/sales/view/'), 'attr') ?>">
    <div class="cw-heading"><div><span class="cw-eyebrow"><?= esc($greeting) ?> · <?= esc(date('l, F j', strtotime($today))) ?></span><h1>My Day</h1><p><?= esc(session('full_name') ?: 'Cashier') ?> · <?= esc($branch['branch_name'] ?? 'Choose a drawer to start') ?> · Philippine time</p></div><div class="cw-actions"><a class="btn btn-primary" href="<?= site_url('cashier/sales') ?>">Open POS</a><a class="btn secondary" href="<?= site_url('cashier/sales/history') ?>">Sales history</a></div></div>
    <?= view('cashier/workspace/_messages') ?>
    <div class="cw-warning" data-shift-changed hidden>Shift status changed in another window. <a href="<?= site_url('cashier/my-day') ?>">Refresh My Day</a> before using the drawer controls.</div>

    <?php if ($activeShift): ?>
    <section class="cw-hero is-open" data-hero-stale aria-label="Your open shift">
        <div><span class="cw-live"><i></i>Shift open</span><h2>Shift #<?= (int) $activeShift['id'] ?></h2>
            <p>Opened <?= esc(DisplayDate::dateTime($activeShift['opened_at'])) ?> · running <strong data-elapsed data-since="<?= esc($activeShift['opened_at'], 'attr') ?>">just now</strong>. It stays open across midnight until you close it.</p></div>
        <dl class="cw-hero-figures"><div><dt>Opening cash</dt><dd><?= $peso($activeShift['opening_cash']) ?></dd></div><div class="is-key"><dt>Expected cash now</dt><dd data-expected-cash data-value="<?= esc((string) $shiftSummary['expected_cash'], 'attr') ?>"><?= $peso($shiftSummary['expected_cash']) ?></dd></div></dl>
        <div class="cw-hero-actions"><a class="btn btn-primary" href="<?= site_url('cashier/shifts/' . $activeShift['id']) ?>">Count and close shift</a><a class="btn secondary" href="#cwMovementCard">Record cash movement</a></div>
    </section>
    <?php else: ?>
    <section class="cw-hero is-idle" aria-label="No open shift">
        <div><span class="cw-live is-idle"><i></i>No shift open</span><h2>Start a shift to begin selling</h2>
            <p>One shared drawer per branch. Count the physical cash before accepting responsibility for it.</p></div><span></span>
        <div class="cw-hero-actions"><?php if ($isCashier): ?><a class="btn btn-primary" href="<?= site_url('cashier/start-shift') ?>">Start shift</a><?php else: ?><a class="btn btn-primary" href="#cwStartCard">Choose a drawer</a><?php endif; ?></div>
    </section>
    <?php endif; ?>

    <form class="cw-filter" method="get" action="<?= site_url('cashier/my-day') ?>">
        <div class="cw-range" role="group" aria-label="Quick date ranges"><?php foreach ($ranges as $label => [$rangeFrom, $rangeTo]): $on = $report['from'] === $rangeFrom && $report['to'] === $rangeTo; ?><a class="cw-chip<?= $on ? ' is-active' : '' ?>" <?= $on ? 'aria-current="true"' : '' ?> href="<?= site_url('cashier/my-day') . ($label === 'Today' ? '' : '?from=' . $rangeFrom . '&to=' . $rangeTo) ?>"><?= esc($label) ?></a><?php endforeach; ?></div>
        <div class="cw-dates"><label>From <input type="date" class="input" name="from" value="<?= esc($report['from'], 'attr') ?>" required></label><label>To <input type="date" class="input" name="to" value="<?= esc($report['to'], 'attr') ?>" required></label><button class="btn secondary btn-sm" type="submit">Show dates</button></div>
        <div class="cw-updated" data-state="live" aria-live="polite"><span class="cw-pulse" aria-hidden="true"></span><small data-last-updated>Updated <?= esc(DisplayDate::dateTime($report['generated_at'])) ?></small><button type="button" class="cw-icon-btn" data-refresh aria-label="Refresh totals now" title="Refresh totals now"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></button></div>
    </form>

    <div class="cw-section-head"><h2>Sales summary</h2><span class="cw-range-label"><?= esc($rangeLabel) ?></span></div>
    <?= view('cashier/workspace/_stats', ['report' => $report], ['saveData' => false]) ?>
    <?php if (($report['unknown_refund_amount'] ?? 0) > 0): ?><div class="cw-warning">Older refunds totaling ₱<?= number_format($report['unknown_refund_amount'], 2) ?> have no recorded payout method. Review them before comparing payment totals.</div><?php endif; ?>
    <?php if ($report['unidentified_refund_lines']): ?><p class="cw-note"><?= (int) $report['unidentified_refund_lines'] ?> older refund line(s) have no event identifier; their amounts are included without guessing the number of refund transactions.</p><?php endif; ?>

    <div class="cw-layout">
        <div class="cw-main"><?= view('cashier/workspace/_tables', ['report' => $report, 'dataTables' => true], ['saveData' => false]) ?></div>
        <aside class="cw-aside">
            <?php if (!$activeShift && !$isCashier): ?>
            <section class="cw-card" id="cwStartCard"><div class="cw-card-head"><h2>Start a shift</h2><p>Count the physical cash before accepting responsibility.</p></div><div class="cw-card-body">
                <form class="cw-form" method="post" action="<?= site_url('cashier/shifts/open') ?>" data-opening-form><?= csrf_field() ?><input type="hidden" name="open_key" value="<?= esc($openKey, 'attr') ?>">
                    <div><label for="cwDrawer">Shared branch drawer</label><select class="input" id="cwDrawer" name="drawer_id" required><option value="">Choose an available drawer</option>
                        <?php foreach ($drawers as $drawer): ?><?php $reserved = $drawer['handover_status'] === 'pending' && (int) $drawer['handover_to_user_id'] !== (int) session('user_id'); $available = empty($drawer['active_shift_id']) && $drawer['status'] === 'active' && !$reserved; ?>
                        <option value="<?= (int) $drawer['id'] ?>" <?= !$available ? 'disabled' : '' ?> data-expected="<?= $drawer['handover_cash'] === null ? '' : esc($drawer['handover_cash'], 'attr') ?>"><?= esc($drawer['branch_name']) ?> · <?= esc($drawer['drawer_name']) ?><?= !empty($drawer['active_shift_id']) ? ' — in use by ' . esc($drawer['current_cashier']) : ($reserved ? ' — handover reserved for ' . esc($drawer['handover_name']) : '') ?></option>
                        <?php endforeach; ?></select><small data-opening-expected>Choose a drawer to see the previous handover count.</small></div>
                    <div><label for="cwOpeningCash">Cash counted at opening</label><div class="cw-money"><span aria-hidden="true">₱</span><input class="input" id="cwOpeningCash" type="number" min="0" max="99999999.99" step="0.01" name="opening_cash" value="<?= esc(old('opening_cash', '', false), 'attr') ?>" inputmode="decimal" required></div></div>
                    <div><label for="cwOpeningNote">Opening note / reason for a different count</label><textarea class="input" id="cwOpeningNote" name="opening_note" maxlength="500"><?= esc(old('opening_note', '', false)) ?></textarea><small>A reason is required when your count differs from the cash left at the last closing.</small></div>
                    <button class="btn btn-primary" type="submit">Start shift</button>
                </form>
                <?php if (!$drawers): ?><p class="cw-note">No branch drawer is configured. An administrator can configure it under Cashier Shifts.</p><?php endif; ?>
            </div></section>
            <?php endif; ?>

            <section class="cw-card" id="cwMovementCard"><div class="cw-card-head"><h2>Cash added or removed</h2><p>Float top-ups, safe drops or documented cash withdrawals.</p></div><div class="cw-card-body">
                <?php if ($activeShift): ?><form class="cw-form" method="post" action="<?= site_url('cashier/shifts/movement') ?>" data-movement-form><?= csrf_field() ?><input type="hidden" name="movement_key" value="<?= esc($movementKey, 'attr') ?>">
                    <div class="cw-seg" role="radiogroup" aria-label="Movement type"><label><input type="radio" name="movement_type" value="cash_in" checked><span>Cash added</span></label><label><input type="radio" name="movement_type" value="cash_out"><span>Cash removed</span></label></div>
                    <div><label for="cwMovementAmount">Amount</label><div class="cw-money"><span aria-hidden="true">₱</span><input class="input" id="cwMovementAmount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" required></div><small data-movement-preview>Enter an amount to see the expected cash afterwards.</small></div>
                    <div><label for="cwMovementReason">Reason</label><input class="input" id="cwMovementReason" name="reason" minlength="3" maxlength="500" required><div class="cw-chips" aria-label="Common reasons"><button type="button" data-reason="Float top-up" data-type="cash_in">Float top-up</button><button type="button" data-reason="Safe drop" data-type="cash_out">Safe drop</button></div></div>
                    <button class="btn secondary" type="submit">Record cash movement</button>
                </form><?php else: ?><div class="cw-empty-state"><svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="10" width="18" height="11" rx="2"/><path d="M7 10V3h10v7M3 15h18"/></svg><p>Open your shift to record cash movements.</p></div><?php endif; ?>
            </div></section>

            <section class="cw-card"><div class="cw-card-head has-side"><div><h2>Held sales <?php if ($heldSales): ?><span class="cw-count is-warn"><?= count($heldSales) ?></span><?php endif; ?></h2><p>Saved carts stay after sign-out. Resolve them before closing the drawer.</p></div></div><div class="cw-card-body">
                <?php if ($heldSales): ?><ul class="cw-list"><?php foreach ($heldSales as $held): ?><li><div class="cw-list-row"><strong><?= esc($held['label'] ?: 'Held sale #' . $held['id']) ?></strong><span class="cw-badge is-warn"><?= count($held['cart']) ?> line<?= count($held['cart']) === 1 ? '' : 's' ?></span></div><small>Held <?= esc(DisplayDate::dateTime($held['held_at'])) ?></small><div class="cw-actions">
                    <form method="post" action="<?= site_url('cashier/sales/resume-held-sale/' . $held['id']) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Resume sale</button></form>
                    <form method="post" action="<?= site_url('cashier/sales/discard-held-sale/' . $held['id']) ?>" onsubmit="return confirm('Discard this held cart?')"><?= csrf_field() ?><button class="btn secondary btn-sm cw-danger" type="submit">Discard</button></form></div></li><?php endforeach; ?></ul>
                <?php else: ?><div class="cw-empty-state"><svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg><p>No held sales waiting.</p></div><?php endif; ?>
            </div></section>

            <section class="cw-card"><div class="cw-card-head has-side"><div><h2>Branch stock alerts <?php if ($alerts): ?><span class="cw-count is-warn"><?= count($alerts) ?></span><?php endif; ?></h2><p>Check stock and expiry before dispensing.</p></div></div><div class="cw-card-body">
                <?php $soon = date('Y-m-d', strtotime($today . ' +30 days')); ?>
                <?php if ($alerts): ?><ul class="cw-list"><?php foreach ($alerts as $item): ?><?php $low = (int) $item['stock'] <= (int) $item['reorder_level']; $expiry = $item['expiration_date'] ?: null; ?>
                    <li><div class="cw-list-row"><div><strong><?= esc($item['product_name']) ?></strong><small><?= (int) $item['stock'] ?> available</small></div><div class="cw-badges">
                        <?php if ($low): ?><span class="cw-badge is-bad">Low stock</span><?php endif; ?>
                        <?php if ($expiry && $expiry <= $today): ?><span class="cw-badge is-bad">Expired</span>
                        <?php elseif ($expiry && $expiry <= $soon): ?><span class="cw-badge is-warn">Expires <?= esc(DisplayDate::date($expiry)) ?></span><?php endif; ?></div></div></li>
                <?php endforeach; ?></ul>
                <?php else: ?><div class="cw-empty-state"><svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M8 12l3 3 5-6"/></svg><p>No low-stock or expiry alerts for this branch.</p></div><?php endif; ?>
                <div class="cw-actions" style="margin-top:14px"><a class="btn secondary btn-sm" href="<?= site_url('products') ?>">View products</a><a class="btn secondary btn-sm" href="<?= site_url('admin/expiry-report') ?>">Expiry report</a></div>
            </div></section>
        </aside>
    </div>

    <section class="cw-card"><div class="cw-card-head"><h2>Your recent shifts</h2><p>Select Review to see a shift's reconciliation and print a copy.</p></div><div class="cw-table-scroll table-wrap"><table class="cw-table cw-table-wide js-datatable" data-dt-empty="Your first shift will appear here once opened." aria-label="Your shifts"><thead><tr><th>Shift</th><th>Branch</th><th>Opened</th><th>Status</th><th class="money">Cash difference</th><th>Review status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($recentShifts as $shift): ?><?php $diff = $shift['cash_difference'] === null ? null : round((float) $shift['cash_difference'], 2); ?><tr><td data-order="<?= (int) $shift['id'] ?>">#<?= (int) $shift['id'] ?></td><td><?= esc($shift['branch_name']) ?></td><td><?= esc(DisplayDate::dateTime($shift['opened_at'])) ?></td><td><span class="cw-badge <?= $shift['status'] === 'open' ? 'is-ok' : '' ?>"><?= esc(ucfirst($shift['status'])) ?></span></td><td class="money" data-order="<?= $diff === null ? '-1000000000000000' : esc((string) $diff, 'attr') ?>"><?php if ($diff === null): ?>—<?php else: ?><span class="cw-diff <?= $diff < 0 ? 'is-short' : ($diff > 0 ? 'is-over' : 'is-even') ?>"><?= $peso($diff) ?></span><small><?= $diff < 0 ? 'Short' : ($diff > 0 ? 'Over' : 'Balanced') ?></small><?php endif; ?></td><td><?php if ($shift['reviewed_at']): ?><span class="cw-badge is-ok">Reviewed</span><?php elseif ($shift['status'] === 'closed'): ?><span class="cw-badge is-warn">Awaiting review</span><?php else: ?><span class="cw-badge is-info">In progress</span><?php endif; ?></td><td><a class="btn secondary btn-sm" href="<?= site_url('cashier/shifts/' . $shift['id']) ?>" aria-label="Review shift #<?= (int)$shift['id'] ?>">Review</a></td></tr><?php endforeach; ?>
        <?php if (!$recentShifts): ?><tr><td colspan="7" class="cw-empty">Your first shift will appear here once opened.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<script src="<?= base_url('assets/js/cashier-workspace.js') ?>?v=20261023" defer></script>
<?= $this->endSection() ?>
