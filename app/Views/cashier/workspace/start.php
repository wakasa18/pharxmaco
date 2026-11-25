<?= $this->extend('layouts/staff') ?><?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/cashier-workspace.css') ?>?v=20261023">
<?php
$available = array_values(array_filter($drawers, static fn($drawer) => empty($drawer['active_shift_id']) && $drawer['status'] === 'active'
    && ($drawer['handover_status'] !== 'pending' || (int)$drawer['handover_to_user_id'] === (int)session('user_id'))));
$selected = (int)old('drawer_id', count($available) === 1 ? $available[0]['id'] : 0, false);
?>
<div class="cashier-workspace cw-start">
    <div class="cw-heading"><div><h1>Start Shift</h1><p><?= esc(session('full_name') ?: 'Cashier') ?><?= $branch ? ' · '.esc($branch['branch_name']) : '' ?></p></div><a class="btn secondary" href="<?= site_url('cashier/my-day') ?>">View My Day</a></div>
    <?= view('cashier/workspace/_messages') ?>
    <div class="cw-grid">
        <section class="cw-card"><div class="cw-card-head"><h2>Count your opening cash</h2><p>Count the physical cash in the shared drawer before you start selling.</p></div><div class="cw-card-body">
        <?php if (!$available): ?>
            <div class="cw-warning" role="status">No drawer is available right now. Wait for the current cashier to close and hand over, or ask an administrator to check the drawer.</div>
            <?php if (!$drawers): ?><p class="cw-note">No active branch drawer is configured for your account.</p><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($drawers as $drawer): ?>
            <?php if ($drawer['active_shift_id']): ?><p class="cw-note"><strong><?= esc($drawer['drawer_name']) ?>:</strong> In use by <?= esc($drawer['current_cashier'] ?: 'another cashier') ?>.</p>
            <?php elseif ($drawer['status'] !== 'active'): ?><p class="cw-note"><strong><?= esc($drawer['drawer_name']) ?>:</strong> Disabled. An administrator can enable it.</p>
            <?php elseif ($drawer['handover_status'] === 'pending'): ?><p class="cw-note"><strong><?= esc($drawer['drawer_name']) ?>:</strong> Handover waiting for <?= esc($drawer['handover_name'] ?: 'the receiving cashier') ?>.</p><?php endif; ?>
        <?php endforeach; ?>
        <form class="cw-form cw-start-form" method="post" action="<?= site_url('cashier/shifts/open') ?>" data-opening-form>
            <?= csrf_field() ?><input type="hidden" name="open_key" value="<?= esc($openKey,'attr') ?>">
            <div><label for="cwDrawer">Shared branch drawer</label><select class="input" id="cwDrawer" name="drawer_id" required <?= !$available ? 'disabled' : '' ?>>
                <option value="">Choose an available drawer</option>
                <?php foreach ($available as $drawer): ?><option value="<?= (int)$drawer['id'] ?>" <?= $selected === (int)$drawer['id'] ? 'selected' : '' ?> data-expected="<?= $drawer['handover_cash'] === null ? '' : esc($drawer['handover_cash'],'attr') ?>"><?= esc($drawer['branch_name']) ?> · <?= esc($drawer['drawer_name']) ?></option><?php endforeach; ?>
            </select><small data-opening-expected>Choose a drawer to see the previous handover count.</small></div>
            <div><label for="cwOpeningCash">Cash counted at opening</label><input class="input" id="cwOpeningCash" name="opening_cash" type="number" min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0.00" value="<?= esc(old('opening_cash','',false),'attr') ?>" required <?= !$available ? 'disabled' : '' ?>><small>Enter the total actually counted, including the change float.</small></div>
            <div><label for="cwOpeningNote">Opening note</label><textarea class="input" id="cwOpeningNote" name="opening_note" maxlength="500" <?= !$available ? 'disabled' : '' ?>><?= esc(old('opening_note','',false)) ?></textarea><small>A reason is required if your count differs from the previous cashier's closing cash left.</small></div>
            <button class="btn btn-primary" type="submit" <?= !$available ? 'disabled' : '' ?>>Start shift and open POS</button>
        </form>
        <?php if (!$available): ?><div class="cw-actions cw-start-refresh"><a class="btn secondary" href="<?= site_url('cashier/start-shift') ?>">Refresh availability</a></div><?php endif; ?>
        </div></section>
        <aside class="cw-card"><div class="cw-card-head"><h2>Ready for the counter</h2><p>Your shift starts with a confirmed cash count.</p></div><div class="cw-card-body">
            <ol class="cw-start-steps"><li><strong>Count the drawer</strong><span>Check the physical cash and any handover from the previous cashier.</span></li><li><strong>Start your shift</strong><span>Record your count and explain any difference.</span></li><li><strong>Continue to POS</strong><span>Complete sales, then use My Day to see your totals and close the drawer.</span></li></ol>
            <p class="cw-note">One cashier can use the shared drawer at a time. If you already have an open shift, signing in resumes it.</p>
        </div></aside>
    </div>
</div><script src="<?= base_url('assets/js/cashier-workspace.js') ?>?v=20261023" defer></script>
<?= $this->endSection() ?>
