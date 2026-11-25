<?= $this->extend('layouts/staff') ?>
<?= $this->section('content') ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
    <div>
        <h1>Suppliers</h1>
        <p>Manage your product suppliers and contacts.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="<?= site_url('admin/suppliers/create') ?>" class="btn btn-primary">+ Add Supplier</a>
        <a href="<?= site_url('admin/suppliers/trash') ?>" class="btn btn-secondary" style="display:flex;align-items:center;gap:5px;">🗑 Trash</a>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<!-- Search/filter bar -->


<div class="card">
    <div class="table-wrap">
        <table class="mobile-record-table js-datatable" data-record-title="1" aria-label="Suppliers">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $i => $s): ?>
                    <tr>
                        <td class="muted" style="font-size:13px;"><?= $i + 1 ?></td>
                        <td style="font-weight:700;font-size:15px;"><?= esc($s['supplier_name']) ?></td>
                        <td><?= esc($s['contact_person'] ?: '—') ?></td>
                        <td style="font-family:'DM Mono',monospace;"><?= esc($s['contact_number'] ?: '—') ?></td>
                        <td style="max-width:220px;overflow-wrap:anywhere;">
                            <?php if (!empty($s['email'])): ?>
                                <a href="mailto:<?= esc($s['email'], 'attr') ?>" title="Email <?= esc($s['supplier_name'], 'attr') ?>"><?= esc($s['email']) ?></a>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:220px;font-size:13px;color:var(--text-2);"><?= esc($s['address'] ?: '—') ?></td>
                        <td><span class="badge"><?= esc($s['product_count'] ?? 0) ?></span></td>
                        <td>
                            <?php if ($s['status'] === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="<?= site_url('admin/suppliers/edit/' . $s['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="post" action="<?= site_url('admin/suppliers/delete/' . $s['id']) ?>" class="inline-action-form"
                                    onsubmit="return confirm('Move this supplier to trash? Products will keep their history.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm" data-busy-label="Moving…">Move to Trash</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--muted);">No suppliers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

<?= $this->endSection() ?>
