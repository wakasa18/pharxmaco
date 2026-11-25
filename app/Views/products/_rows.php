<?php helper('inflector'); $batchMap ??= []; $batchNumbers ??= []; ?>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                    <?php
                        $expiry = $product['branch_expiration_date'] ?? $product['expiration_date'] ?? null;
                        $isExpired = $expiry && strtotime($expiry) <= strtotime(date('Y-m-d'));
                        $isNearExpiry = $expiry && !$isExpired && strtotime($expiry) <= strtotime(date('Y-m-d', strtotime('+30 days')));
                        $statusValue = $product['branch_status'] ?? $product['status'] ?? 'inactive';
                        $costPrice = $product['branch_cost_price'] ?? $product['cost_price'] ?? 0;
                        $sellingPrice = $product['branch_price'] ?? $product['price'] ?? 0;
                        $stockValue = $product['branch_stock'] ?? $product['stock'] ?? 0;
                        $cashierBranchId = (int)(session('branch_id') ?? 0);
                        $rowBranchId = (int)($product['branch_id'] ?? 0);
                        $canCashierEdit = session('role') === 'cashier' && $cashierBranchId === $rowBranchId;
                        $unit = trim((string) ($product['unit'] ?? ''));
                        $unitKey = mb_strtolower($unit);
                        $singularUnit = in_array($unitKey, ['pc', 'pcs'], true) ? 'piece' : singular($unitKey ?: 'unit');
                        $pluralUnit = plural($singularUnit);
                        // Default for "Add stock": the unexpired batch with stock that is nearest to expiry
                        // (batchMap is already ordered nearest expiry first, batches without an expiry last).
                        $defaultBatch = null;
                        $todayDate = date('Y-m-d');
                        foreach (($batchMap[(int) ($product['branch_product_id'] ?? 0)] ?? []) as $candidateBatch) {
                            if (!empty($candidateBatch['expiration_date']) && $candidateBatch['expiration_date'] <= $todayDate) continue;
                            $defaultBatch = $candidateBatch;
                            break;
                        }
                        $defaultLot = $defaultBatch['batch_no'] ?? '';
                        $defaultExpiry = $defaultBatch['expiration_date'] ?? '';
                        // Keep measurement abbreviations unchanged; this is display wording, not unit conversion.
                        if (in_array($unitKey, ['mg', 'g', 'kg', 'ml', 'l', 'mcg', 'iu'], true)) $singularUnit = $pluralUnit = $unit;
                    ?>
                    <tr>
                        <td style="color:#9ca3af;font-size:12px;"><?= esc($product['id']) ?></td>
                        <td><strong style="font-size:13px;"><?= esc($product['product_name']) ?></strong>
                            <div><?= view('partials/medicine_type_badge', ['medicineTypeValue'=>$product['medicine_type'] ?? null], ['saveData'=>false]) ?></div>
                        </td>
                        <td style="font-size:12.5px;"><?= esc($product['branch_name'] ?? '—') ?></td>
                        <td style="font-size:12.5px;"><?= esc($product['category_name'] ?? '—') ?></td>
                        <td style="font-size:12.5px;"><?= esc($product['manufacturer'] ?: '—') ?></td>
                        <td style="font-size:12.5px;"><?= esc($product['supplier_name'] ?: '—') ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?= esc($product['sku'] ?: '—') ?></td>
                        <td style="font-size:12.5px;"><?= esc($product['unit'] ?: '—') ?></td>
                        <td style="font-size:13px;">₱<?= number_format((float)$costPrice, 2) ?></td>
                        <td style="font-size:13px;font-weight:600;">₱<?= number_format((float)$sellingPrice, 2) ?></td>
                        <td>
                            <?php
                                $reorderLevel = (int) ($product['branch_reorder_level'] ?? $product['reorder_level'] ?? 0);
                                $isOutOfStock = (int) $stockValue <= 0;
                                $isLowStock = !$isOutOfStock && (int) $stockValue <= $reorderLevel;
                            ?>
                            <?php if ($canCashierEdit): ?>
                                <?php
                                    $stockTokenKey = 'stock_adjustment_' . (int) $product['id'] . '_' . (int) $product['branch_id'];
                                    $stockToken = session($stockTokenKey);
                                    if (!is_string($stockToken) || $stockToken === '') {
                                        $stockToken = bin2hex(random_bytes(24));
                                        session()->set($stockTokenKey, $stockToken);
                                    }
                                ?>
                                <form method="post" action="<?= site_url('products/update-stock') ?>" class="stock-update-form" data-unit-singular="<?= esc($singularUnit, 'attr') ?>" data-unit-plural="<?= esc($pluralUnit, 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="stock_token" value="<?= esc($stockToken, 'attr') ?>">
                                    <input type="hidden" name="product_id" value="<?= esc($product['id']) ?>">
                                    <input type="hidden" name="branch_id" value="<?= esc($product['branch_id']) ?>">
                                    <input type="hidden" name="old_stock" value="<?= esc($stockValue) ?>">
                                    <?php if ($defaultBatch): ?><input type="hidden" name="base_batch_id" value="<?= (int) $defaultBatch['id'] ?>"><?php endif; ?>
                                    <strong class="stock-current">Current stock: <?= (int) $stockValue ?> <?= esc((int) $stockValue === 1 ? $singularUnit : $pluralUnit) ?></strong>
                                    <label class="stock-field">
                                        <span>Stock action</span>
                                        <select name="stock_mode" class="input" aria-label="Stock action for <?= esc($product['product_name'], 'attr') ?>">
                                            <option value="add">Add stock</option>
                                            <option value="remove">Remove stock</option>
                                        </select>
                                    </label>
                                    <label class="stock-field">
                                        <span>Quantity <span aria-hidden="true">*</span></span>
                                        <input type="number" name="stock" value="" class="input" aria-label="Quantity to add or remove for <?= esc($product['product_name'], 'attr') ?>" placeholder="Quantity" min="1" max="1000000" step="1" required>
                                    </label>
                                    <label class="stock-field stock-add-only">
                                        <span>Batch / lot no.</span>
                                        <input type="text" name="batch_no" class="input" maxlength="60" placeholder="Optional" value="<?= esc((string) $defaultLot, 'attr') ?>" data-default="<?= esc((string) $defaultLot, 'attr') ?>" aria-label="Batch or lot number for <?= esc($product['product_name'], 'attr') ?>">
                                    </label>
                                    <label class="stock-field stock-add-only">
                                        <span>Expiry date</span>
                                        <input type="date" name="expiration_date" class="input" min="<?= esc(date('Y-m-d', strtotime('+1 day')), 'attr') ?>" value="<?= esc((string) $defaultExpiry, 'attr') ?>" data-default="<?= esc((string) $defaultExpiry, 'attr') ?>" aria-label="Expiry date of the new batch for <?= esc($product['product_name'], 'attr') ?>">
                                    </label>
                                    <?php if ($defaultBatch): ?>
                                    <span class="stock-batch-note stock-add-only" aria-live="polite" data-has-default="1"></span>
                                    <?php endif; ?>
                                    <label class="stock-field stock-remove-only" hidden>
                                        <span>From batch</span>
                                        <select name="batch_id" class="input" disabled aria-label="Batch to remove stock from for <?= esc($product['product_name'], 'attr') ?>">
                                            <option value="">Earliest expiry first</option>
                                            <?php foreach (($batchMap[(int) ($product['branch_product_id'] ?? 0)] ?? []) as $rowBatch): ?>
                                                <option value="<?= (int) $rowBatch['id'] ?>"><?= esc(\App\Libraries\StockBatches::label($rowBatch['batch_no'] ?? null, $batchNumbers[(int) $rowBatch['id']] ?? null)) ?> · <?= esc($rowBatch['expiration_date'] ? \App\Libraries\DisplayDate::date($rowBatch['expiration_date']) : 'no expiry') ?> · <?= (int) $rowBatch['quantity_remaining'] ?> left</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="stock-field stock-reason-field">
                                        <span>Reason / reference <span aria-hidden="true">*</span></span>
                                        <input type="text" name="remarks" class="input stock-reason" aria-label="Stock change reason for <?= esc($product['product_name'], 'attr') ?>" placeholder="e.g. Delivery DR-1024" minlength="3" maxlength="200" required>
                                    </label>
                                    <div class="stock-quick" role="group" aria-label="Quick reasons">
                                        <?php foreach (['add' => ['Delivery', 'Customer return', 'Transfer in', 'Count correction'], 'remove' => ['Damaged', 'Expired - disposed', 'Returned to supplier', 'Transfer out', 'Count correction']] as $quickMode => $quickReasons): ?>
                                            <?php foreach ($quickReasons as $quickReason): ?>
                                                <button type="button" class="stock-chip" data-mode="<?= $quickMode ?>" data-reason="<?= esc($quickReason, 'attr') ?>"<?= $quickMode === 'remove' ? ' hidden' : '' ?>><?= esc($quickReason) ?></button>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="stock-preview" aria-live="polite">Enter a quantity to see the new balance.</span>
                                    <button type="submit" class="btn btn-primary btn-sm stock-submit" data-busy-label="Saving…">Add stock</button>
                                </form>
                            <?php else: ?>
                                <span style="font-weight:600;"><?= esc($stockValue) ?></span>
                            <?php endif; ?>
                            <?php if ($isOutOfStock): ?>
                                <br><span class="badge badge-danger" style="margin-top:4px;">Out of Stock</span>
                            <?php elseif ($isLowStock): ?>
                                <br><span class="badge badge-warning" style="margin-top:4px;">Low Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="stock-expiry">
                            <?php if (!empty($expiry)): ?>
                                <time datetime="<?= esc($expiry, 'attr') ?>"><?= esc(\App\Libraries\DisplayDate::date($expiry)) ?></time>
                                <?php if ($isExpired): ?>
                                    <span class="badge badge-danger">Expired</span>
                                <?php elseif ($isNearExpiry): ?>
                                    <span class="badge badge-warning">Near</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                            <?php if ((int) ($product['batch_count'] ?? 0) > 0): ?>
                                <a class="stock-batches-link" href="<?= site_url('products/batches/' . (int) $product['branch_product_id']) ?>"><?= (int) $product['batch_count'] === 1 ? '1 batch' : (int) $product['batch_count'] . ' batches (nearest expiry shown)' ?></a>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($statusValue === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (session('role') === 'admin'): ?>
                                <div class="action-group">
                                    <a href="<?= site_url('products/edit/' . $product['id'] . '?branch_id=' . urlencode($product['branch_id'] ?? ($branchId ?? ''))) ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <?php if ((int)($product['branch_id'] ?? 0) > 0): ?>
                                    <form method="post" action="<?= site_url('products/delete/' . $product['id']) ?>" class="inline-action-form"
                                        onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?= esc('Move ' . $product['product_name'] . ' to Trash in ' . ($product['branch_name'] ?? 'this branch') . ' only? Its stock will be retained. Other branches will keep this product.', 'attr') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="branch_id" value="<?= (int)($product['branch_id'] ?? 0) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-busy-label="Moving…">Move to Trash</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($canCashierEdit): ?>
                                <span class="badge badge-success">Your Branch</span>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;">View Only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="14" class="muted" style="text-align:center;padding:24px;">No products found.</td></tr>
                <?php endif; ?>
