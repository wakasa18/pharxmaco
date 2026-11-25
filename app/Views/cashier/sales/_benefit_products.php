<?php foreach ($cart as $item): ?>
                            <?php $eligible = (string) ($item['senior_pwd_eligible'] ?? '') === '1'; ?>
                            <label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;overflow-wrap:anywhere;">
                                <input type="checkbox" class="benefit-product" name="benefit_products[<?= (int) $item['product_id'] ?>]" value="1"
                                    data-product-id="<?= (int) $item['product_id'] ?>" data-tax-treatment="<?= esc($item['tax_treatment'] ?? '', 'attr') ?>"
                                    <?= !$eligible ? 'disabled data-unavailable="1"' : '' ?> <?= is_array($oldBenefitProducts) && isset($oldBenefitProducts[$item['product_id']]) ? 'checked' : '' ?>>
                                <span><?= esc($item['product_name']) ?><?= !$eligible ? ' — eligibility not confirmed or not eligible' : '' ?></span>
                            </label>
                        <?php endforeach; ?>
