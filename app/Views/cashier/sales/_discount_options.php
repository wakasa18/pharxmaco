<option value="auto" <?= $discountChoice === 'auto' ? 'selected' : '' ?>>No Discount</option>
                                <?php foreach (\App\Libraries\StatutoryDiscount::OPTIONS as $kind => $label): ?>
                                <option value="<?= esc($kind) ?>" data-benefit-kind="<?= esc($kind) ?>" <?= $discountChoice === $kind ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                                <?php foreach (($discounts ?? []) as $d): ?>
                                    <?php
                                        $minPurchase = (float) ($d['minimum_purchase'] ?? 0);
                                        $scope = $d['applies_to'] ?? 'all';
                                        if (in_array($scope, ['product','category'], true)) continue;
                                        $scopeLabel = 'All products';
                                        if ($scope === 'category') {
                                            $scopeLabel = !empty($d['category_name'])
                                                ? 'Category: ' . $d['category_name']
                                                : 'Selected category';
                                        }
                                    ?>
                                    <option value="<?= $d['id'] ?>"
                                        <?= $discountChoice === (string) $d['id'] ? 'selected' : '' ?>
                                        data-promo-name="<?= esc($d['discount_name'], 'attr') ?>"
                                        data-type="<?= esc($d['discount_type']) ?>"
                                        data-value="<?= esc($d['discount_value']) ?>"
                                        data-minimum="<?= esc($d['minimum_purchase'] ?? 0) ?>"
                                        data-max="<?= esc($d['max_discount_amount'] ?? '') ?>"
                                        data-applies-to="<?= esc($scope) ?>"
                                        data-category-id="<?= esc($d['category_id'] ?? '') ?>"
                                        data-product-id="<?= esc($d['product_id'] ?? '') ?>">
                                        Promotion: <?= esc($d['discount_name']) ?>
                                        (<?= $d['discount_type'] === 'percentage'
                                            ? rtrim(rtrim(number_format((float)$d['discount_value'],2),'0'),'.').'%'
                                            : '₱'.number_format((float)$d['discount_value'],2) ?>)
                                        — <?= esc($scopeLabel) ?><?php if ($minPurchase > 0): ?>, min. ₱<?= number_format($minPurchase, 2) ?><?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
