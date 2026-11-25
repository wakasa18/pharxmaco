<script id="payment-reference-rules" type="application/json"><?= json_encode(\App\Libraries\PaymentReference::rules(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= base_url('assets/js/payment-reference.js') ?>?v=20261028-1" defer></script>
