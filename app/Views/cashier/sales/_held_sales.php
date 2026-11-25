<?php foreach ($heldSales as $i => $held): ?>
                <?php
                    $heldItemCount = 0;
                    $heldTotal = 0.0;
                    foreach (($held['cart'] ?? []) as $hi) {
                        $heldItemCount += (int) ($hi['quantity'] ?? 0);
                        $heldTotal += (float) ($hi['subtotal'] ?? 0);
                    }
                ?>
                <div class="held-sale-row">
                    <div class="held-sale-info">
                        <div class="held-sale-label"><?= !empty($held['label']) ? esc($held['label']) : 'Held sale #' . ($i + 1) ?></div>
                        <div class="held-sale-meta">
                            <?= $heldItemCount ?> item<?= $heldItemCount === 1 ? '' : 's' ?> · ₱<?= number_format($heldTotal, 2) ?>
                            · <?= esc(\App\Libraries\DisplayDate::time($held['held_at'] ?? 'now')) ?>
                        </div>
                    </div>
                    <div class="held-sale-actions">
                        <form method="post" action="<?= site_url('cashier/sales/resume-held-sale/' . $i) ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary">Resume</button>
                        </form>
                        <form method="post" action="<?= site_url('cashier/sales/discard-held-sale/' . $i) ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary">Discard</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
