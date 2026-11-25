<?php
$expiryLimits = \App\Libraries\ExpiryDatePolicy::limits();
$expiryFieldId = $expiryFieldId ?? 'product-expiry';
$expiryFieldName = $expiryFieldName ?? 'expiration_date';
$expiryValue = is_scalar($expiryValue ?? null) ? (string) $expiryValue : '';
$expiryOriginal = is_scalar($expiryOriginal ?? null) ? (string) $expiryOriginal : '';
$expiryRequired = $expiryRequired ?? false;
$expiryUnchanged = $expiryOriginal !== '' && $expiryValue === $expiryOriginal;
$expiryLegacyValue = $expiryUnchanged && ($expiryValue < $expiryLimits['minimum'] || $expiryValue > $expiryLimits['maximum']);
$expiryMaxLabel = \App\Libraries\DisplayDate::date($expiryLimits['maximum']);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/expiry-date-field.css') ?>?v=20261028-3">
<div class="expiry-date-control" data-expiry-date-control data-minimum="<?= esc($expiryLimits['minimum'], 'attr') ?>" data-maximum="<?= esc($expiryLimits['maximum'], 'attr') ?>" data-original="<?= esc($expiryOriginal, 'attr') ?>">
    <input class="input" id="<?= esc($expiryFieldId, 'attr') ?>" type="date" name="<?= esc($expiryFieldName, 'attr') ?>"
        value="<?= esc($expiryValue, 'attr') ?>" <?= $expiryLegacyValue ? '' : 'min="' . esc($expiryLimits['minimum'], 'attr') . '" max="' . esc($expiryLimits['maximum'], 'attr') . '"' ?>
        aria-describedby="<?= esc($expiryFieldId, 'attr') ?>-help" <?= $expiryRequired ? 'required' : '' ?> data-expiry-input>
    <p class="form-help" id="<?= esc($expiryFieldId, 'attr') ?>-help">Use a date after today and on or before <?= esc($expiryMaxLabel) ?>.<?= $expiryRequired ? '' : ' Leave blank if no expiry applies.' ?><?= $expiryOriginal !== '' ? ' The existing date may be kept unchanged.' : '' ?></p>
</div>
<script src="<?= base_url('assets/js/expiry-date-field.js') ?>?v=20261028-3" defer></script>
