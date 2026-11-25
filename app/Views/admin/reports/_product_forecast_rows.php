<?php
$trendBadges = [
    'up'     => ['badge-success', 'Rising'],
    'down'   => ['badge-danger', 'Falling'],
    'steady' => ['badge-neutral', 'Steady'],
];
foreach (($productForecast ?? []) as $item):
    [$trendClass, $trendLabel] = $trendBadges[$item['direction']] ?? $trendBadges['steady'];
?>
<tr>
    <td><span class="rp-rank-name"><?= esc($item['product_name']) ?></span></td>
    <td class="num"><?= number_format((int) $item['history_qty']) ?></td>
    <td class="num"><?= number_format((int) $item['next_qty']) ?></td>
    <td class="num amount rp-forecast-value">₱<?= number_format((float) $item['next_sales'], 2) ?></td>
    <td class="num"><?= number_format((int) $item['total_qty']) ?></td>
    <td class="num amount rp-forecast-value">₱<?= number_format((float) $item['total_sales'], 2) ?></td>
    <td><span class="badge <?= esc($trendClass, 'attr') ?>"><?= esc($trendLabel) ?></span></td>
</tr>
<?php endforeach; ?>
