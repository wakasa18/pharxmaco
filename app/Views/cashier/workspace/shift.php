<?= $this->extend('layouts/staff') ?><?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/cashier-workspace.css') ?>?v=20261023">
<div class="cashier-workspace">
    <div class="cw-heading"><div><h1>Shift #<?= (int) $shift['id'] ?></h1><p><?= esc($shift['full_name']) ?> · <?= esc($shift['branch_name']) ?> · <?= esc($shift['drawer_name']) ?></p></div><div class="cw-actions"><a class="btn secondary" href="<?= site_url('cashier/my-day') ?>">My Day</a><a class="btn secondary" href="<?= site_url('cashier/shifts/' . $shift['id'] . '/print') ?>" target="_blank" rel="noopener">Print shift report</a><?php if (session('role') === 'admin'): ?><a class="btn secondary" href="<?= site_url('admin/cashier-shifts') ?>">Cashier shifts</a><?php endif; ?></div></div>
    <?= view('cashier/workspace/_messages') ?>
    <div class="cw-status"><div><strong><?= esc(ucfirst($shift['status'])) ?></strong><small>Opened <?= esc(\App\Libraries\DisplayDate::dateTime($shift['opened_at'])) ?><?= $shift['closed_at'] ? ' · Closed ' . esc(\App\Libraries\DisplayDate::dateTime($shift['closed_at'])) : '' ?></small></div><span class="cw-tag"><?= $shift['status'] === 'closed' ? 'Saved closing totals' : 'Live shift totals' ?></span></div>
    <?= view('cashier/workspace/_stats', ['report'=>$summary], ['saveData'=>false]) ?>
    <div class="cw-grid">
        <section class="cw-card"><div class="cw-card-head"><h2>Drawer reconciliation</h2><p>Cash retained after change. Exchange credit is excluded from money received.</p></div><div class="cw-card-body">
            <?= view('cashier/workspace/_ledger', ['shift'=>$shift,'summary'=>$summary], ['saveData'=>false]) ?>
            <?php if ($shift['opening_expected'] !== null): ?><p class="cw-note">Previous closing left ₱<?= number_format($shift['opening_expected'],2) ?>. Opening difference: ₱<?= number_format($shift['opening_difference'],2) ?>.</p><?php endif; ?>
            <?php if ($shift['opening_note']): ?><p class="cw-note"><strong>Opening note:</strong> <?= esc($shift['opening_note']) ?></p><?php endif; ?>
            <?php if ($shift['closing_note']): ?><p class="cw-note"><strong>Closing note:</strong> <?= esc($shift['closing_note']) ?></p><?php endif; ?>
            <?php if ($shift['handover_to_user_id']): ?><p class="cw-note"><strong>Handover:</strong> <?= esc($shift['handover_name'] ?: 'Cashier #' . $shift['handover_to_user_id']) ?> · <?= esc($shift['handover_status']) ?><?= $shift['handover_accepted_shift_id'] ? ' in shift #' . (int)$shift['handover_accepted_shift_id'] : '' ?></p><?php endif; ?>
        </div></section>
        <section class="cw-card"><div class="cw-card-head"><h2><?= $shift['status'] === 'open' ? 'Count and close' : 'Closing review' ?></h2><p><?= $shift['status'] === 'open' ? 'Finish all transactions and count the physical drawer before closing.' : 'Later refunds and corrections belong to the shift that processes them.' ?></p></div><div class="cw-card-body">
        <?php if ($shift['status'] === 'open'): ?>
            <?php if ($heldSales): ?><div class="cw-warning"><?= count($heldSales) ?> held sale(s) are waiting. Resume or discard them from My Day before closing.</div><?php endif; ?>
            <?php $onBehalf = (int)$shift['user_id'] !== (int)session('user_id'); ?>
            <?php if ($onBehalf): ?><p class="cw-note">You are closing another cashier's shift. Confirm they have stopped using the drawer and include a reason.</p><?php endif; ?>
            <form class="cw-form" method="post" action="<?= site_url('cashier/shifts/' . $shift['id'] . '/close') ?>" data-closing-form data-expected="<?= esc($summary['expected_cash'],'attr') ?>" data-on-behalf="<?= $onBehalf ? '1' : '0' ?>">
                <?= csrf_field() ?><input type="hidden" name="shift_revision" value="<?= esc($summary['revision'],'attr') ?>">
                <div><label for="cwCounted">Cash counted</label><input class="input" id="cwCounted" type="number" name="counted_cash" min="0" max="99999999.99" step="0.01" required inputmode="decimal"><small data-count-difference>Enter your physical count to see the over / short amount.</small></div>
                <div><label for="cwLeft">Cash left for the next shift</label><input class="input" id="cwLeft" type="number" name="cash_left" min="0" max="99999999.99" step="0.01" required inputmode="decimal"><small data-closing-removal>The rest will be recorded as removed at closing.</small></div>
                <div><label for="cwNext">Handover to</label><select class="input" id="cwNext" name="handover_to_user_id"><option value="">No named handover (end of day)</option><?php foreach ($nextCashiers as $cashier): ?><option value="<?= (int)$cashier['id'] ?>"><?= esc($cashier['full_name']) ?></option><?php endforeach; ?></select><small>A named handover reserves the drawer until that cashier counts and accepts it, or an administrator releases it.</small></div>
                <div><label for="cwClosingNote">Closing note / reason for difference</label><textarea class="input" id="cwClosingNote" name="closing_note" maxlength="500" <?= $onBehalf ? 'required minlength="3"' : '' ?>></textarea></div>
                <?php if ($onBehalf && $heldSales): ?><label class="cw-check"><input type="checkbox" name="discard_held" value="1" required> Discard this cashier's <?= count($heldSales) ?> held cart(s) and record the reason above.</label><?php endif; ?>
                <button class="btn btn-primary" type="submit">Save count and close shift</button><small>Closing totals are final. Refresh and recount if new drawer activity arrives.</small>
            </form>
        <?php elseif ($review): ?><p><strong>Reviewed by <?= esc($review['full_name']) ?></strong></p><p class="cw-note"><?= esc($review['review_note']) ?> · <?= esc(\App\Libraries\DisplayDate::dateTime($review['reviewed_at'])) ?></p>
        <?php elseif (session('role') === 'admin'): ?><form class="cw-form" method="post" action="<?= site_url('admin/cashier-shifts/review/shift/' . $shift['id']) ?>"><?= csrf_field() ?><div><label for="cwReviewNote">Review note</label><textarea class="input" id="cwReviewNote" name="review_note" minlength="3" maxlength="500" required></textarea></div><button class="btn btn-primary">Record manager review</button></form>
        <?php else: ?><p class="cw-note">Awaiting administrator review.</p><?php endif; ?>
        <?php if (session('role') === 'admin' && $shift['handover_status'] === 'pending'): ?><hr><form class="cw-form" method="post" action="<?= site_url('admin/cashier-shifts/' . $shift['id'] . '/release-handover') ?>"><?= csrf_field() ?><div><label for="cwReleaseReason">Release handover reason</label><input class="input" id="cwReleaseReason" name="reason" minlength="3" maxlength="500" required></div><button class="btn secondary">Release named handover</button></form><?php endif; ?>
        </div></section>
    </div>
    <?= view('cashier/workspace/_tables', ['report'=>$summary, 'dataTables'=>true], ['saveData'=>false]) ?>
    <section class="cw-card"><div class="cw-card-head"><h2>Shift record</h2><p>Opening, closing and handover actions.</p></div><div class="cw-card-body"><ul class="cw-list"><?php foreach ($actions as $action): ?><li><strong><?= esc(ucwords(str_replace('_',' ',$action['action']))) ?> · <?= esc($action['full_name']) ?></strong><small><?= esc(\App\Libraries\DisplayDate::dateTime($action['created_at'])) ?><?= $action['note'] ? ' · '.esc($action['note']) : '' ?></small></li><?php endforeach; ?></ul></div></section>
</div><script src="<?= base_url('assets/js/cashier-workspace.js') ?>?v=20261023" defer></script>
<?= $this->endSection() ?>
