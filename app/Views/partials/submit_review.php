<?php
$reviewFields = ['product_name','sku','category_id','unit','medicine_type','senior_pwd_eligible','generic_name','strength',
    'tax_treatment','cost_price','price','stock','reorder_level','expiration_date','status','manufacturer','supplier_id',
    'discount_name','description','discount_type','discount_value','applies_to','product_id','minimum_purchase',
    'max_discount_amount','start_date','end_date'];
$reviewBaseline = array_intersect_key($reviewOriginal ?? [], array_flip($reviewFields));
$reviewId = $reviewFormId . '-review';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/product-create-review.css') ?>?v=20261020">
<link rel="stylesheet" href="<?= base_url('assets/css/form-submit-review.css') ?>?v=20261020">
<script type="application/json" id="<?= esc($reviewFormId) ?>-review-original"><?= json_encode($reviewBaseline, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<div class="inventory-modal product-review-overlay" id="<?= esc($reviewId) ?>" aria-hidden="true">
    <section class="inventory-modal-dialog product-review-dialog" role="dialog" aria-modal="true" aria-labelledby="<?= esc($reviewId) ?>-title" aria-describedby="<?= esc($reviewId) ?>-description">
        <header class="inventory-modal-header">
            <div class="inventory-modal-title-wrap">
                <h2 class="inventory-modal-title" id="<?= esc($reviewId) ?>-title"><?= esc($reviewTitle) ?></h2>
                <p class="inventory-modal-subtitle" id="<?= esc($reviewId) ?>-description"><?= ($reviewMode ?? 'edit') === 'edit' ? 'Check the current and new values before saving.' : 'Check the discount details before creating it.' ?></p>
            </div>
            <button type="button" class="inventory-modal-close" data-submit-review-close aria-label="Close review">&times;</button>
        </header>
        <div class="product-review-body" tabindex="0">
            <?php if (!empty($reviewContext)): ?><p class="submit-review-context"><?= esc($reviewContext) ?></p><?php endif; ?>
            <p class="submit-review-count" data-review-count role="status"></p>
            <p class="submit-review-empty" data-review-empty hidden>No changes to save. Return to the form to make an edit.</p>
            <div data-review-rows></div>
            <div class="submit-review-note" data-review-note hidden></div>
        </div>
        <footer class="product-review-actions">
            <button type="button" class="btn btn-secondary" data-submit-review-close data-review-back>Back to edit</button>
            <button type="submit" class="btn btn-primary" form="<?= esc($reviewFormId) ?>" data-review-confirm data-busy-label="Saving…">Confirm &amp; save</button>
        </footer>
    </section>
</div>
<script src="<?= base_url('assets/js/form-submit-review.js') ?>?v=20261125-1" defer></script>
