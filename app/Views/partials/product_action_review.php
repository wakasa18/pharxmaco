<?php
/*
 * One shared "overview before you submit" dialog for the Products actions that
 * do not already have one (stock changes, add-to-branch, Trash, restore,
 * permanent delete, batch correction). Forms opt in with data-action-review="…";
 * assets/js/product-action-review.js fills this dialog and then re-submits the
 * original form, so validation, CSRF and the controller rules are unchanged.
 */
?>
<link rel="stylesheet" href="<?= base_url('assets/css/product-create-review.css') ?>?v=20261020">
<link rel="stylesheet" href="<?= base_url('assets/css/product-action-review.css') ?>?v=20261125">
<div class="inventory-modal product-review-overlay" id="product-action-review" aria-hidden="true">
    <section class="inventory-modal-dialog product-review-dialog" role="dialog" aria-modal="true" aria-labelledby="product-action-review-title" aria-describedby="product-action-review-description">
        <header class="inventory-modal-header">
            <div class="inventory-modal-title-wrap">
                <h2 class="inventory-modal-title" id="product-action-review-title" data-par-title>Review</h2>
                <p class="inventory-modal-subtitle" id="product-action-review-description" data-par-subtitle>Check the details below before continuing.</p>
            </div>
            <button type="button" class="inventory-modal-close" data-par-close aria-label="Close overview">&times;</button>
        </header>
        <div class="product-review-body" tabindex="0" aria-label="Details to confirm">
            <div class="product-review-identity">
                <h3 data-par-name></h3>
                <p data-par-meta></p>
            </div>
            <div data-par-sections></div>
            <section class="product-review-section par-notes-section" data-par-notes-wrap hidden>
                <ul class="par-notes" data-par-notes></ul>
            </section>
        </div>
        <footer class="product-review-actions">
            <button type="button" class="btn btn-secondary" data-par-close data-par-back>Back</button>
            <button type="button" class="btn btn-primary" data-par-confirm>Confirm</button>
        </footer>
    </section>
</div>
<script src="<?= base_url('assets/js/product-action-review.js') ?>?v=20261125" defer></script>
