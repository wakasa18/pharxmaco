<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>
<?php helper('inflector'); ?>
<style>
.stock-update-form{display:grid;grid-template-columns:minmax(160px,1.2fr) minmax(110px,1fr);gap:10px;min-width:280px;max-width:340px;white-space:normal;text-align:left}
.stock-update-form .input{min-width:0;width:100%;min-height:44px}
.stock-update-form .stock-current,.stock-update-form .stock-reason-field,.stock-update-form .stock-preview,.stock-update-form .stock-submit,.stock-update-form .stock-batch-note,.stock-update-form .stock-quick{grid-column:1/-1}
.stock-update-form .stock-quick{display:flex;flex-wrap:wrap;gap:6px;margin-top:-4px}
.stock-update-form .stock-chip{font:inherit;font-size:12px;font-weight:600;line-height:1;padding:7px 10px;min-height:32px;border-radius:999px;border:1px solid var(--border,#d5d9e6);background:var(--surface,#fff);color:var(--muted,#4b5563);cursor:pointer}
.stock-update-form .stock-chip:hover,.stock-update-form .stock-chip:focus-visible{border-color:var(--primary,#4f46e5);color:var(--primary,#4f46e5)}
.stock-update-form .stock-chip[hidden]{display:none}
.stock-update-form .stock-batch-note{font-size:12px;color:var(--muted);white-space:normal;margin-top:-4px}
.stock-update-form .stock-batch-note[hidden]{display:none}
.stock-update-form .stock-field{display:flex;flex-direction:column;gap:6px;min-width:0;margin:0}
.stock-update-form .stock-field[hidden]{display:none}
.stock-update-form .stock-field>span{font-size:12px;font-weight:600;color:var(--muted)}
.stock-update-form .stock-submit:disabled{opacity:.55;cursor:not-allowed}
.stock-update-form .stock-preview{font-size:12px;color:var(--muted);white-space:normal}
.stock-expiry{display:flex;flex-direction:column;align-items:flex-start;gap:6px;min-width:145px;white-space:normal;text-align:left}
.stock-expiry::before{content:'Expiry';font-size:12px;font-weight:600;color:var(--muted)}
.stock-expiry time{font-size:12px;white-space:nowrap}
.content .medicine-type-filter-form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:16px;border-bottom:1px solid var(--line)}
.content .medicine-type-filter-form>.field{display:grid;gap:6px;margin:0;min-width:0;flex:1 1 220px;max-width:300px}
.content .medicine-type-filter-form .label{margin:0}
.content .medicine-type-filter-form .input,.content .medicine-type-filter-form .product-filter-actions>.btn{box-sizing:border-box;margin:0;min-height:46px;line-height:1.4;padding:10px 14px}
.content .medicine-type-filter-form .input{width:100%}
.product-filter-actions{display:flex;gap:8px;flex-wrap:wrap}
.product-filter-note{flex-basis:100%;margin:0;color:var(--muted);font-size:12px;line-height:1.5}
.catalogue-table .dt-filter-row .dt-server-filter{min-width:175px}
.product-filter-feedback{padding:12px 16px;border-bottom:1px solid var(--line);font-size:13px}
.product-filter-feedback[hidden],.product-filter-feedback button[hidden]{display:none}
.medicine-type-badge{margin-top:5px}
@media(max-width:600px){.content .medicine-type-filter-form>.field{flex-basis:100%;max-width:none}.product-filter-actions{width:100%}.product-filter-actions>.btn{flex:1}}
@media(max-width:700px){.stock-update-form{grid-template-columns:minmax(0,1fr);min-width:0;max-width:none;width:100%}.content .mobile-record-ready td:has(.stock-update-form){grid-column:1/-1}.stock-expiry{min-width:0}.stock-expiry::before{display:none}}
</style>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div>
        <h1>Products</h1>
        <p>Manage product records, stock, pricing and expiry by branch.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php if (session('role') === 'cashier'): ?>
            <a href="<?= site_url('products/catalog') ?>" class="btn btn-primary">+ Add from Existing Products</a>
        <?php endif; ?>
        <a href="<?= site_url('products/create') ?>" class="btn <?= session('role') === 'cashier' ? 'btn-secondary' : 'btn-primary' ?>">
            <?= session('role') === 'cashier' ? '+ Create New Product' : '+ Add Product' ?>
        </a>
        <?php if (session('role') === 'admin'): ?>
            <a href="<?= site_url('products/trash') ?>" class="btn btn-secondary"><?= view('partials/icon', ['name' => 'trash']) ?> Trash</a>
        <?php endif; ?>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('errors')): ?>
    <div class="alert danger"><ul style="margin:0;padding-left:20px;"><?php foreach (session()->getFlashdata('errors') as $message): ?><li><?= esc($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Product List</h2>
            <p>
                <?php if (session('role') === 'cashier'): ?>
                    Showing products for your assigned branch. Add or remove stock by entering the number of units and a reason.
                <?php else: ?>
                    Filter by branch, search and edit products. Move to Trash affects only the selected branch.
                <?php endif; ?>
            </p>
        </div>
    </div>

    

    <?php
        $manufacturer = $manufacturer ?? '';
        $manufacturers = $manufacturers ?? [];
        if ($manufacturer !== '' && !in_array($manufacturer, $manufacturers, true)) $manufacturers[] = $manufacturer;
        $clearFiltersUrl = site_url('products') . (($branchId ?? '') !== '' ? '?' . http_build_query(['branch_id' => $branchId]) : '');
    ?>
    <form id="products-filter-form" data-auto-filter="off" method="get" action="<?= site_url('products') ?>" class="medicine-type-filter-form" aria-label="Product filters">
        <?php foreach (['keyword'=>$keyword ?? '', 'branch_id'=>$branchId ?? '', 'category_id'=>$categoryId ?? '', 'status'=>$status ?? ''] as $filterKey=>$filterValue): ?>
            <?php if ($filterValue !== ''): ?><input type="hidden" name="<?= esc($filterKey) ?>" value="<?= esc($filterValue, 'attr') ?>"><?php endif; ?>
        <?php endforeach; ?>
        <div class="field">
            <label class="label" for="products-medicine-type">Medicine type</label>
            <select class="input" id="products-medicine-type" name="medicine_type">
                <?php foreach (\App\Libraries\MedicineType::FILTERS as $value=>$label): ?>
                    <option value="<?= esc($value) ?>" <?= ($medicineType ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" data-dt-filter-field>
            <label class="label" for="products-stock-status">Stock status</label>
            <select class="input" id="products-stock-status" name="stock_filter" form="products-filter-form">
                <?php foreach (['' => 'All stock', 'in' => 'In stock', 'low' => 'Low / out of stock', 'out' => 'Out of stock', 'healthy' => 'Above reorder level'] as $value => $label): ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= ($stockFilter ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" data-dt-filter-field>
            <label class="label" for="products-manufacturer">Manufacturer</label>
            <select class="input" id="products-manufacturer" name="manufacturer" form="products-filter-form">
                <option value="">All manufacturers</option>
                <?php foreach ($manufacturers as $name): ?>
                    <option value="<?= esc($name, 'attr') ?>" <?= $manufacturer === $name ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="product-filter-actions">
            <button type="submit" class="btn btn-secondary">Apply filters</button>
            <a class="btn btn-secondary" href="<?= esc($clearFiltersUrl, 'attr') ?>">Clear filters</a>
        </div>
        <p class="product-filter-note">Stock status uses each branch's recorded quantity and reorder level. Check expiry separately.</p>
    </form>
    <div class="product-filter-feedback" role="status" aria-live="polite" hidden><span></span> <button type="button" class="btn btn-secondary btn-sm" hidden>Retry</button></div>
    <?php if (!empty($tableTruncated)): ?>
        <p class="dt-cap-note" role="status">This list is very long, so only the first <?= number_format(3000) ?> records are loaded here.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="mobile-record-table catalogue-table js-datatable" data-dt-refreshable data-record-title="1" data-dt-empty="No products found." data-dt-link-ignore="keyword,branch_id,category_id,status,stock_filter,manufacturer,medicine_type" aria-label="Products">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Product</th>
                    <th>Branch</th>
                    <th>Category</th>
                    <th data-dt-filter="off" data-dt-server-filter="products-manufacturer">Manufacturer</th>
                    <th>Supplier</th>
                    <th>SKU</th>
                    <th>Unit</th>
                    <th>Cost</th>
                    <th>Price</th>
                    <th data-dt-filter="off" data-dt-server-filter="products-stock-status">Stock</th>
                    <th title="Items expire at the start of their expiration date.">Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?= view('products/_rows', ['products' => $products, 'branchId' => $branchId ?? '', 'batchMap' => $batchMap ?? [], 'batchNumbers' => $batchNumbers ?? []], ['saveData' => false]) ?>
            </tbody>
        </table>
    </div>

    </div>

<script src="<?= base_url('assets/js/products-table.js') ?>?v=20261122-quick-reasons" defer></script>

<?= $this->endSection() ?>
