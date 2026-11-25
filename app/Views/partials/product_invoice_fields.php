<?php
    $invoiceProduct = $product ?? [];
    $defaultTaxTreatment = isset($invoiceProduct['id']) ? (string) ($invoiceProduct['tax_treatment'] ?? '') : (trim((string) ($invoiceProduct['tax_treatment'] ?? '')) ?: 'taxable');
    $selectedTaxTreatment = old('tax_treatment', $defaultTaxTreatment, false);
?>
<div class="field">
    <label class="label" for="product-medicine-type">Medicine type</label>
    <select id="product-medicine-type" name="medicine_type" class="input">
        <?php foreach (\App\Libraries\MedicineType::OPTIONS as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= old('medicine_type', $invoiceProduct['medicine_type'] ?? '', false) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="form-help">Use Branded or Generic for medicines, and Non-medicine for supplies or other items. Confirm from product packaging; generic name identifies the ingredient.</span>
</div>
<div class="field">
    <label class="label" for="product-benefit-eligibility">Senior Citizen/PWD medicine benefit</label>
    <select id="product-benefit-eligibility" name="senior_pwd_eligible" class="input">
        <?php foreach (['' => 'Not confirmed', '1' => 'Eligible medicine / qualifying prescribed supplement', '0' => 'Not eligible'] as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= (string) old('senior_pwd_eligible', $invoiceProduct['senior_pwd_eligible'] ?? '', false) === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="form-help">Confirm the product qualifies before enabling. Covers both branded and generic medicines. Required prescriptions and beneficiary documents are checked at checkout.</span>
</div>
<div class="product-form-grid">
    <div class="field">
        <label class="label" for="product-generic-name">Generic medicine name</label>
        <input id="product-generic-name" type="text" name="generic_name" class="input" maxlength="150"
            value="<?= esc(old('generic_name', $invoiceProduct['generic_name'] ?? '', false), 'attr') ?>" placeholder="e.g. Paracetamol">
    </div>
    <div class="field">
        <label class="label" for="product-strength">Strength</label>
        <input id="product-strength" type="text" name="strength" class="input" maxlength="80"
            value="<?= esc(old('strength', $invoiceProduct['strength'] ?? '', false), 'attr') ?>" placeholder="e.g. 500 mg or 125 mg / 5 mL">
    </div>
</div>
<div class="field">
    <label class="label" for="product-tax-treatment">Tax classification</label>
    <select id="product-tax-treatment" name="tax_treatment" class="input">
        <?php foreach (['' => 'Not specified', 'taxable' => 'VATable', 'vat_exempt' => 'VAT-exempt', 'zero_rated' => 'Zero-rated'] as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $selectedTaxTreatment === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="form-help">Choose the applicable classification for VAT invoice breakdowns. Non-VAT invoices show Non-VAT sales.</span>
</div>
