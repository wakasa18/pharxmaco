<?php $overviewId = $overviewFormId . '-overview'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/transaction-overview.css') ?>?v=20261023">
<script type="application/json" id="<?= esc($overviewFormId) ?>-overview-data"><?= json_encode($overviewData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<div class="inventory-modal transaction-overview" id="<?= esc($overviewId) ?>" aria-hidden="true">
    <section class="inventory-modal-dialog transaction-overview-dialog" role="dialog" aria-modal="true" aria-labelledby="<?= esc($overviewId) ?>-title" aria-describedby="<?= esc($overviewId) ?>-description">
        <header class="inventory-modal-header"><div class="inventory-modal-title-wrap"><h2 class="inventory-modal-title" id="<?= esc($overviewId) ?>-title"><?= esc($overviewTitle) ?></h2><p class="inventory-modal-subtitle" id="<?= esc($overviewId) ?>-description">Check the transaction details before confirming. Nothing has been saved yet.</p></div><button type="button" class="inventory-modal-close" data-overview-close aria-label="Close overview">&times;</button></header>
        <div class="transaction-overview-body" tabindex="0"><p data-overview-status role="status" class="transaction-overview-status" hidden></p><div data-overview-content></div></div>
        <footer class="transaction-overview-footer"><label class="transaction-overview-ack"><input type="checkbox" data-overview-ack><span>I checked the items, amounts and changes shown above.</span></label><div class="transaction-overview-actions"><button type="button" class="btn btn-secondary" data-overview-close data-overview-back>Back to edit</button><button type="button" class="btn <?= esc($overviewConfirmClass ?? 'btn-primary') ?>" data-overview-confirm disabled><?= esc($overviewConfirmLabel) ?></button></div></footer>
    </section>
</div>
<script src="<?= base_url('assets/js/transaction-overview.js') ?>?v=20261023" defer></script>
