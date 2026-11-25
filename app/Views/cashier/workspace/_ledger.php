<dl class="cw-ledger">
    <?php $ledger = ['opening_cash'=>'Opening cash', 'cash_received'=>'Cash payments received', 'cash_paid_out'=>'Cash payouts', 'cash_added'=>'Cash added', 'cash_removed'=>'Cash removed', 'expected_cash'=>'Expected cash']; ?>
    <?php foreach ($ledger as $key => $label): ?><?php $value = match($key) { 'cash_received' => $summary['payments']['cash']['received'], 'cash_paid_out' => -$summary['payments']['cash']['paid_out'], 'cash_removed' => -$summary['cash_removed'], default => $summary[$key] }; ?>
    <div class="<?= $key === 'expected_cash' ? 'cw-balance' : '' ?>"><dt><?= esc($label) ?></dt><dd>₱<?= number_format($value, 2) ?></dd></div>
    <?php endforeach; ?>
    <?php if ($shift['status'] === 'closed'): ?>
    <?php foreach (['counted_cash'=>'Cash counted', 'cash_difference'=>'Over / short', 'cash_left'=>'Left in drawer', 'banked_cash'=>'Removed at closing'] as $key => $label): ?><div><dt><?= esc($label) ?></dt><dd>₱<?= number_format($summary[$key], 2) ?></dd></div><?php endforeach; ?>
    <?php endif; ?>
</dl>
