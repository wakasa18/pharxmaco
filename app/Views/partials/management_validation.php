<script type="application/json" id="management-input-rules"><?= json_encode(\App\Validation\ManagementRules::definitions(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= base_url('assets/js/management-validation.js') ?>?v=20261020-1" defer></script>
