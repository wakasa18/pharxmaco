<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\ReceiptInvoice as InvoiceConfig;

final class ReceiptInvoice
{
    /** Called inside checkout/exchange's transaction; failed sales roll back their serial. */
    public static function capture(BaseConnection $db, ?array $discount = null, ?InvoiceConfig $config = null, ?array $beneficiary = null, array $productPromotions = []): string
    {
        $config ??= config(InvoiceConfig::class);
        self::validate($config);
        $serial = $config->serialEnd > 0 ? self::reserveSerial($db, $config) : null;
        return self::snapshot($discount, $config, $serial, $beneficiary, $productPromotions);
    }

    public static function snapshot(?array $discount = null, ?InvoiceConfig $config = null, ?string $serial = null, ?array $beneficiary = null, array $productPromotions = []): string
    {
        $config ??= config(InvoiceConfig::class);
        self::validate($config);
        return json_encode([
            'version' => 2,
            'tin' => trim($config->tin),
            'tax_status' => $config->taxStatus,
            'vat_rate' => $config->vatRate,
            'serial' => $serial,
            'discount_name' => trim((string) ($discount['discount_name'] ?? '')),
            'statutory_discount' => $beneficiary,
            'product_promotions' => $productPromotions,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private static function validate(InvoiceConfig $config): void
    {
        if (!in_array($config->taxStatus, ['', 'non_vat', 'vat'], true)
            || !is_finite($config->vatRate) || $config->vatRate <= 0 || $config->vatRate > 100
            || mb_strlen($config->tin) > 32
            || !preg_match('/^[A-Za-z0-9-]{0,24}$/D', $config->serialPrefix)
            || $config->serialDigits < 1 || $config->serialDigits > 12
            || $config->serialStart < 1 || $config->serialEnd < 0
            || ($config->serialEnd > 0 && ($config->serialEnd < $config->serialStart
                || $config->serialEnd >= 10 ** $config->serialDigits))) {
            throw new InvoiceConfigurationException('Invoice settings are invalid. Ask an administrator to check the tax status and invoice number range.');
        }
    }

    private static function reserveSerial(BaseConnection $db, InvoiceConfig $config): string
    {
        // Retain the counter for this prefix even when its authorized range changes.
        $key = hash('sha256', $config->serialPrefix);
        $sqlite = $db->DBDriver === 'SQLite3';
        $db->query(($sqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE')
            . ' INTO receipt_invoice_sequences (series_key, next_number) VALUES (?, ?)',
            [$key, $config->serialStart]);
        $row = $db->query('SELECT next_number FROM receipt_invoice_sequences WHERE series_key = ?'
            . ($sqlite ? '' : ' FOR UPDATE'), [$key])->getRowArray();
        if (!$row) throw new \RuntimeException('Could not reserve the invoice serial number.');
        $number = max($config->serialStart, (int) $row['next_number']);
        if ($number > $config->serialEnd) {
            throw new InvoiceConfigurationException('The configured invoice number range is exhausted. Ask an administrator to configure the next registered range.');
        }
        if (!$db->table('receipt_invoice_sequences')->where('series_key', $key)->update(['next_number' => $number + 1])) {
            throw new \RuntimeException('Could not save the invoice serial counter.');
        }
        return $config->serialPrefix . str_pad((string) $number, $config->serialDigits, '0', STR_PAD_LEFT);
    }

    public static function itemSnapshot(array $product): array
    {
        $treatment = $product['tax_treatment'] ?? null;
        return [
            'generic_name_snapshot' => trim((string) ($product['generic_name'] ?? '')) ?: null,
            'strength_snapshot' => trim((string) ($product['strength'] ?? '')) ?: null,
            'tax_treatment_snapshot' => in_array($treatment, ['taxable', 'vat_exempt', 'zero_rated'], true) ? $treatment : null,
        ];
    }

    public static function discountBreakdown(array $sale): ?array
    {
        $benefit = StatutoryDiscount::forSale($sale);
        $pricing = $benefit['pricing'] ?? null;
        if (!$benefit) {
            $metadata = json_decode((string)($sale['receipt_metadata'] ?? ''), true);
            $offers = $metadata['product_promotions'] ?? null;
            if (is_array($offers) && $offers) {
                $total = 0;
                foreach ($offers as $offer) {
                    if (!is_array($offer) || !is_string($offer['name'] ?? null) || !is_numeric($offer['amount'] ?? null)
                        || !is_finite((float)$offer['amount']) || (float)$offer['amount'] <= 0) return null;
                    $total += DiscountPolicy::cents((float)$offer['amount']);
                }
                if ($total !== DiscountPolicy::cents((float)($sale['discount_amount'] ?? 0))) return null;
                return ['statutory'=>0.0, 'promotion'=>$total/100.0, 'promotion_name'=>implode(' + ',array_unique(array_column($offers,'name'))), 'statutory_name'=>''];
            }
        }
        if (!is_array($pricing) || ($pricing['policy'] ?? '') !== 'more_favorable_per_item'
            || !is_numeric($pricing['statutory_discount'] ?? null) || !is_numeric($pricing['promotion_discount'] ?? null)) return null;
        $statutory = (float)$pricing['statutory_discount']; $promotion = (float)$pricing['promotion_discount'];
        if (!is_finite($statutory) || !is_finite($promotion) || $statutory < 0 || $promotion < 0
            || DiscountPolicy::cents($statutory) + DiscountPolicy::cents($promotion)
                !== DiscountPolicy::cents((float)($sale['discount_amount'] ?? 0))) return null;
        return ['statutory'=>$statutory, 'promotion'=>$promotion,
            'promotion_name'=>is_string($pricing['promotion_name'] ?? null) ? $pricing['promotion_name'] : 'Product promo',
            'statutory_name'=>StatutoryDiscount::OPTIONS[$benefit['kind']]];
    }

    public static function data(array $sale, array $items): array
    {
        $metadata = json_decode((string) ($sale['receipt_metadata'] ?? ''), true);
        $recorded = is_array($metadata) && ($metadata['version'] ?? null) === 2
            && is_string($metadata['tin'] ?? null)
            && is_string($metadata['discount_name'] ?? null)
            && in_array($metadata['tax_status'] ?? null, ['', 'non_vat', 'vat'], true)
            && array_key_exists('serial', $metadata)
            && ($metadata['serial'] === null || (is_string($metadata['serial']) && $metadata['serial'] !== ''));
        $status = $recorded ? $metadata['tax_status'] : '';
        $result = [
            'recorded' => $recorded,
            'serial' => $recorded ? ($metadata['serial'] ?? (string) ($sale['invoice_no'] ?? '')) : (string) ($sale['invoice_no'] ?? ''),
            'series_recorded' => $recorded && $metadata['serial'] !== null,
            'tin' => $recorded ? (trim($metadata['tin']) ?: 'Not configured') : 'Not recorded',
            'tax_status' => ['vat' => 'VAT-registered', 'non_vat' => 'Non-VAT'][$status] ?? ($recorded ? 'Not configured' : 'Not recorded'),
            'discount_name' => $recorded ? $metadata['discount_name'] : '',
            'beneficiary' => $recorded ? StatutoryDiscount::forSale($sale) : null,
            'discount_breakdown' => $recorded ? self::discountBreakdown($sale) : null,
            'vat_exemption' => array_sum(array_map(static fn ($item) => DiscountPolicy::cents((float) ($item['vat_exemption_amount'] ?? 0)), $items)) / 100.0,
            'tax_rows' => [], 'tax_recorded' => false,
        ];
        if (!in_array($status, ['vat', 'non_vat'], true) || !$items) return $result;

        $netTotal = 0;
        $buckets = ['taxable' => 0, 'vat_exempt' => 0, 'zero_rated' => 0];
        foreach ($items as $item) {
            $net = DiscountPolicy::cents((float) ($item['subtotal'] ?? 0))
                - DiscountPolicy::cents((float) ($item['discount_applied'] ?? 0))
                - DiscountPolicy::cents((float) ($item['vat_exemption_amount'] ?? 0));
            if ($net < 0) return $result;
            $netTotal += $net;
            if ($status === 'vat') {
                $treatment = StatutoryDiscount::kind($item['statutory_discount_kind'] ?? null)
                    ? 'vat_exempt' : ($item['tax_treatment_snapshot'] ?? '');
                if (!array_key_exists($treatment, $buckets)) return $result;
                $buckets[$treatment] += $net;
            }
        }
        // Never invent a tax breakdown for legacy/imported amounts that do not reconcile.
        if ($netTotal !== DiscountPolicy::cents((float) ($sale['final_total'] ?? 0))) return $result;
        if ($status === 'non_vat') {
            $result['tax_rows'] = ['Non-VAT sales' => $netTotal / 100.0, 'VAT charged' => 0.0];
        } else {
            $rate = (float) ($metadata['vat_rate'] ?? 0);
            if (!is_finite($rate) || $rate <= 0 || $rate > 100) return $result;
            $vat = (int) round($buckets['taxable'] * $rate / (100 + $rate));
            $rateLabel = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
            $result['tax_rows'] = [
                'VATable sales' => ($buckets['taxable'] - $vat) / 100.0,
                'VAT amount (' . $rateLabel . '%)' => $vat / 100.0,
                'VAT-exempt sales' => $buckets['vat_exempt'] / 100.0,
                'Zero-rated sales' => $buckets['zero_rated'] / 100.0,
            ];
        }
        $result['tax_recorded'] = true;
        return $result;
    }
}
