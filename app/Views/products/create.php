<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/product-create-review.css') ?>?v=20261020-1">

<div class="editor-page editor-page--wide">

<?php
$isCashier = $isCashier ?? ((string) session('role') === 'cashier');
$assignedBranch = $assignedBranch ?? null;
$assignedBranchId = (int) ($assignedBranch['id'] ?? session('branch_id') ?? 0);
$assignedBranchName = (string) ($assignedBranch['branch_name'] ?? 'Assigned branch');
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
    <div>
        <h1><?= $isCashier ? 'Add Product to Your Branch' : 'Add Product' ?></h1>
        <p>
            <?= $isCashier
                ? 'Create a product record for ' . esc($assignedBranchName) . '. The branch cannot be changed.'
                : 'Create a new product and assign it to a branch.' ?>
        </p>
    </div>
    <a href="<?= site_url('products') ?>" class="btn btn-secondary">← Back</a>
</div>

<?= view('partials/form_messages') ?>

<?php if ($isCashier): ?>
    <div class="product-role-note">
        <p>Already in the catalog? <a href="<?= site_url('products/catalog') ?>">Choose an existing product</a> to reuse its name, SKU, category, and unit.</p>
        <strong>Your branch:</strong>
        This product will be added only to <strong><?= esc($assignedBranchName) ?></strong>.
        When the SKU already belongs to an existing product, the system will add that product to your branch instead of creating a duplicate.
        Ask an administrator when shared product information needs to be corrected later.
    </div>
<?php endif; ?>

<form method="post" action="<?= site_url('products/store') ?>" id="product-create-form" data-management-form="product" data-review-branch="<?= esc($isCashier ? $assignedBranchName : '', 'attr') ?>">
    <?= csrf_field() ?>
        <p class="editor-form-note">Fields marked <span class="required-mark">*</span> are required.</p>

    <div class="product-create-layout">
        <div>
            <div class="card" style="margin-bottom:18px;">
                <div class="card-head"><div><h2>Product details</h2><p>Name, category, SKU, and selling unit.</p></div></div>
                <div class="card-body">
                    <div class="product-form-grid">
                        <div class="field">
                            <label class="label" for="editor-field-1">Product Name <span style="color:var(--danger);">*</span></label>
                            <input id="editor-field-1" type="text" name="product_name" class="input" maxlength="150" autocomplete="off"
                                value="<?= old('product_name') ?>" required>
                        </div>
                        <div class="field">
                            <label class="label" for="editor-field-2">Category <span style="color:var(--danger);">*</span></label>
                            <select id="editor-field-2" name="category_id" class="input" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= esc($cat['id']) ?>" <?= old('category_id') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= esc($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="product-form-grid">
                        <div class="field">
                            <label class="label" for="editor-field-3">SKU <span class="required-mark">*</span></label>
                            <input id="editor-field-3" type="text" name="sku" class="input" maxlength="50" autocomplete="off"
                                value="<?= old('sku') ?>" placeholder="e.g. PAR500-001" required>
                            <span class="form-help">Use the same SKU when the product already exists in another branch.</span>
                            <span class="form-help">Required. Use letters, numbers, hyphens, underscores, dots or slashes.</span>
                        </div>
                        <?= view('partials/product_unit_field', ['unitOptions' => $unitOptions ?? \App\Libraries\ProductUnit::OPTIONS], ['saveData' => false]) ?>
                    </div>

                    <?= view('partials/product_invoice_fields', [], ['saveData' => false]) ?>
                </div>
            </div>

            <div class="card" style="margin-bottom:18px;">
                <div class="card-head"><div><h2>Pricing and stock</h2><p>Set prices in pesos and manage stock for this branch.</p></div></div>
                <div class="card-body">
                    <div class="product-form-grid">
                        <div class="field">
                            <label class="label" for="editor-field-5">Cost price (₱) <span style="color:var(--danger);">*</span></label>
                            <input id="editor-field-5" type="number" step="0.01" min="0" name="cost_price" class="input"
                                value="<?= old('cost_price') ?>" required>
                        </div>
                        <div class="field">
                            <label class="label" for="editor-field-6">Selling price (₱) <span style="color:var(--danger);">*</span></label>
                            <input id="editor-field-6" type="number" step="0.01" min="0" name="price" class="input"
                                value="<?= old('price') ?>" required>
                        </div>
                    </div>

                    <div class="product-form-grid">
                        <div class="field">
                            <label class="label" for="editor-field-7">Initial Stock <span style="color:var(--danger);">*</span></label>
                            <input id="editor-field-7" type="number" min="0" name="stock" class="input"
                                value="<?= old('stock', 0) ?>" required>
                        </div>
                        <div class="field">
                            <label class="label" for="editor-field-8">Reorder Level <span style="color:var(--danger);">*</span></label>
                            <input id="editor-field-8" type="number" min="0" name="reorder_level" class="input"
                                value="<?= old('reorder_level', 0) ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="editor-field-9">Expiration Date</label>
                        <?= view('partials/expiry_date_field', ['expiryFieldId' => 'editor-field-9', 'expiryValue' => old('expiration_date', ''), 'expiryOriginal' => ''], ['saveData' => false]) ?>
                    </div>

                    <div class="field">
                        <label class="label" for="editor-field-batch">Batch / Lot Number</label>
                        <input id="editor-field-batch" type="text" name="batch_no" class="input" maxlength="60" value="<?= esc(old('batch_no', ''), 'attr') ?>" placeholder="Optional, from the manufacturer's label">
                        <span class="form-help">Opening stock is saved as the first batch with this expiration date.</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Manufacturer & Supplier</h2></div>
                <div class="card-body">
                    <div class="field">
                        <label class="label" for="editor-field-10">Manufacturer</label>
                        <input id="editor-field-10" type="text" name="manufacturer" class="input" maxlength="150" autocomplete="organization"
                            value="<?= old('manufacturer') ?>" placeholder="e.g. Unilab, GSK, Pfizer">
                    </div>
                    <div class="field">
                        <label class="label" for="editor-field-11">Supplier</label>
                        <select id="editor-field-11" name="supplier_id" class="input">
                            <option value="">— No Supplier —</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= esc($sup['id']) ?>" <?= old('supplier_id') == $sup['id'] ? 'selected' : '' ?>>
                                    <?= esc($sup['supplier_name']) ?>
                                    <?= !empty($sup['contact_person']) ? ' — ' . esc($sup['contact_person']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isCashier): ?>
                            <small style="font-size:12px;color:var(--muted);">
                                <a href="<?= site_url('admin/suppliers/create') ?>" target="_blank" rel="noopener">+ Add new supplier</a>
                            </small>
                        <?php else: ?>
                            <span class="form-help">Ask an administrator when the supplier is not yet listed.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="product-create-side">
            <div class="card">
                <div class="card-head"><h2>Branch Assignment</h2></div>
                <div class="card-body">
                    <?php if ($isCashier): ?>
                        <input type="hidden" name="branch_id" value="<?= esc($assignedBranchId) ?>">
                        <div class="assigned-branch-box">
                            <strong><?= esc($assignedBranchName) ?></strong>
                            <span class="form-help">Locked to the branch assigned to your cashier account.</span>
                        </div>
                    <?php else: ?>
                        <div class="field">
                            <label class="label" for="editor-field-12">Assign to Branch <span class="required-mark">*</span></label>
                            <select id="editor-field-12" name="branch_id" class="input" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $br): ?>
                                    <option value="<?= esc($br['id']) ?>" <?= old('branch_id') == $br['id'] ? 'selected' : '' ?>>
                                        <?= esc($br['branch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-help">Initial stock and expiry will be recorded for this branch.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


        </div>
    </div>
    <?= view('partials/editor_actions', ['cancelUrl' => site_url('products'), 'submitLabel' => ($isCashier ? 'Add to My Branch' : 'Save Product')]) ?>
</form>

</div>

<div class="inventory-modal product-review-overlay" id="product-create-review" aria-hidden="true">
    <section class="inventory-modal-dialog product-review-dialog" role="dialog" aria-modal="true" aria-labelledby="product-review-title" aria-describedby="product-review-description">
        <header class="inventory-modal-header">
            <div class="inventory-modal-title-wrap">
                <h2 class="inventory-modal-title" id="product-review-title">Product overview</h2>
                <p class="inventory-modal-subtitle" id="product-review-description">Check the details below before adding this product.</p>
            </div>
            <button class="inventory-modal-close" type="button" data-product-review-close aria-label="Close product overview">&times;</button>
        </header>
        <div class="product-review-body" tabindex="0" aria-label="Product details to save">
            <div class="product-review-identity">
                <h3 data-product-review="product_name"></h3>
                <p>Adding to <strong data-product-review="branch_id"></strong></p>
            </div>
            <section class="product-review-section" aria-labelledby="product-review-details-title">
                <h3 id="product-review-details-title">Product details</h3>
                <dl class="product-review-fields">
                    <div><dt>SKU</dt><dd data-product-review="sku"></dd></div>
                    <div><dt>Category</dt><dd data-product-review="category_id"></dd></div>
                    <div><dt>Medicine type</dt><dd data-product-review="medicine_type"></dd></div>
                    <div><dt>Unit</dt><dd data-product-review="unit"></dd></div>
                    <div><dt>Generic medicine name</dt><dd data-product-review="generic_name"></dd></div>
                    <div><dt>Strength</dt><dd data-product-review="strength"></dd></div>
                    <div><dt>Tax classification</dt><dd data-product-review="tax_treatment"></dd></div>
                    <div><dt>Senior Citizen/PWD benefit</dt><dd data-product-review="senior_pwd_eligible"></dd></div>
                </dl>
            </section>
            <section class="product-review-section" aria-labelledby="product-review-stock-title">
                <h3 id="product-review-stock-title">Pricing and stock</h3>
                <dl class="product-review-fields">
                    <div><dt>Cost price per unit</dt><dd data-product-review="cost_price"></dd></div>
                    <div><dt>Selling price per unit</dt><dd data-product-review="price"></dd></div>
                    <div><dt>Initial stock</dt><dd data-product-review="stock"></dd></div>
                    <div><dt>Reorder level</dt><dd data-product-review="reorder_level"></dd></div>
                    <div><dt>Expiration date</dt><dd data-product-review="expiration_date"></dd></div>
                </dl>
            </section>
            <section class="product-review-section" aria-labelledby="product-review-supplier-title">
                <h3 id="product-review-supplier-title">Manufacturer and supplier</h3>
                <dl class="product-review-fields">
                    <div><dt>Manufacturer</dt><dd data-product-review="manufacturer"></dd></div>
                    <div><dt>Supplier</dt><dd data-product-review="supplier_id"></dd></div>
                </dl>
            </section>
        </div>
        <footer class="product-review-actions">
            <button class="btn btn-secondary" type="button" data-product-review-close id="product-review-edit">Back to edit</button>
            <button class="btn btn-primary" type="submit" form="product-create-form" id="product-review-save" data-busy-label="Saving…">Confirm &amp; save</button>
        </footer>
    </section>
</div>
<script src="<?= base_url('assets/js/product-create-review.js') ?>?v=20261020-1" defer></script>
<?= $this->endSection() ?>
