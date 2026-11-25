<?php
$options = $unitOptions ?? \App\Libraries\ProductUnit::OPTIONS;
$selectedUnit = old('unit', $product['unit'] ?? '', false);
$selectedUnit = is_string($selectedUnit) ? trim($selectedUnit) : '';
?>
<div class="field">
    <label class="label" for="editor-field-4">Unit <span class="required-mark">*</span></label>
    <select id="editor-field-4" name="unit" class="input" required>
        <option value="">Select a unit</option>
        <?php foreach ($options as $value => $label): ?>
            <option value="<?= esc($value, 'attr') ?>" <?= $selectedUnit === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="form-help">Choose the unit used for stock quantities and prices.</span>
</div>
