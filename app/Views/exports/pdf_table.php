<?php
$tableRows = $rows ?? [];
?>
<div class="report-section">
    <h3 class="section-title"><?= esc($title) ?></h3>
    <?php if (!empty($note)): ?><p class="section-note"><?= esc($note) ?></p><?php endif; ?>
    <table class="data">
        <thead><tr><?php foreach ($columns as $column): ?><th style="width:<?= (float)$column['width'] ?>%;" class="<?= !empty($column['num']) ? 'num' : '' ?>"><?= esc($column['label']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($tableRows as $row): ?>
            <tr><?php foreach ($columns as $index => $column): ?><td class="<?= !empty($column['num']) ? 'num' : '' ?>"><?= nl2br(esc((string)($row[$index] ?? '-'))) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if (!$tableRows): ?><tr><td colspan="<?= count($columns) ?>" class="empty"><?= esc($empty ?? 'No records matched the selected filters.') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
