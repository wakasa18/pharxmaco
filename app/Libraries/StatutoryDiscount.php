<?php

namespace App\Libraries;

use Config\ReceiptInvoice;
use InvalidArgumentException;

final class StatutoryDiscount
{
    public const OPTIONS = ['senior_citizen' => 'Senior Citizen', 'pwd' => 'PWD'];

    public static function kind(mixed $value): ?string
    {
        return is_string($value) && isset(self::OPTIONS[$value]) ? $value : null;
    }

    public static function forSale(array $sale): ?array
    {
        $metadata = json_decode((string) ($sale['receipt_metadata'] ?? ''), true);
        $benefit = $metadata['statutory_discount'] ?? null;
        return is_array($benefit) && self::kind($benefit['kind'] ?? null) ? $benefit : null;
    }

    /** Prices remain VAT-inclusive catalog prices. Each selected line is for this beneficiary only. */
    public static function quote(string $kind, array $items, array $input, ?ReceiptInvoice $config = null): array
    {
        $config ??= config(ReceiptInvoice::class);
        if (!self::kind($kind)) throw new InvalidArgumentException('Select a valid Senior Citizen or PWD benefit.');
        if (!in_array($config->taxStatus, ['vat', 'non_vat'], true)
            || ($config->taxStatus === 'vat' && $config->vatRate !== 12.0)) {
            throw new InvalidArgumentException('Configure the seller VAT/Non-VAT status and the 12% VAT rate before using Senior Citizen/PWD benefits.');
        }
        $beneficiary = ['kind' => $kind];
        foreach (['beneficiary_name' => ['name', 120], 'beneficiary_id' => ['id', 60]] as $field => [$key, $limit]) {
            $raw = $input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : '';
            if (mb_strlen($value) < 2 || mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new InvalidArgumentException('Enter the beneficiary name and valid ID/reference number.');
            }
            $beneficiary[$key] = $value;
        }
        if (($input['benefit_verified'] ?? null) !== '1') {
            throw new InvalidArgumentException('Confirm that the beneficiary ID, required supporting documents and exclusive-use eligibility were checked.');
        }
        $beneficiary['verified'] = true;
        $selected = $input['benefit_products'] ?? null;
        if (!is_array($selected) || !$selected || count($selected) > 1000) {
            throw new InvalidArgumentException('Select at least one eligible product for the beneficiary.');
        }
        $bases = [];
        $plans = [];
        $vatTotal = 0;
        foreach ($selected as $id => $checked) {
            if (!ctype_digit((string) $id) || (int) $id < 1 || (string) (int) $id !== (string) $id
                || $checked !== '1' || !isset($items[(int) $id])) {
                throw new InvalidArgumentException('The selected benefit products do not match the cart.');
            }
            $item = $items[(int) $id];
            if ((string) ($item['senior_pwd_eligible'] ?? '') !== '1') {
                throw new InvalidArgumentException('A selected product has not been confirmed as eligible for Senior Citizen/PWD medicine benefits.');
            }
            $gross = DiscountPolicy::cents((float) $item['subtotal']);
            if ($gross <= 0) throw new InvalidArgumentException('Eligible products must have a positive selling price.');
            $treatment = $item['tax_treatment'] ?? null;
            if ($config->taxStatus === 'vat' && !in_array($treatment, ['taxable', 'vat_exempt', 'zero_rated'], true)) {
                throw new InvalidArgumentException('Set the tax classification of each eligible product before checkout.');
            }
            $base = $config->taxStatus === 'vat' && $treatment === 'taxable'
                ? (int) round($gross * 100 / 112) : $gross;
            $vat = $gross - $base;
            $vatTotal += $vat;
            $bases[(int) $id] = $base / 100.0;
            $plans[(int) $id] = ['vat_exemption' => $vat / 100.0, 'kind' => $kind];
        }
        $amount = DiscountPolicy::amount(array_sum($bases), ['type' => 'percentage', 'value' => 20, 'minimum' => 0, 'maximum' => null]);
        foreach (DiscountAllocator::allocate($bases, $amount) as $id => $discount) $plans[$id]['discount'] = $discount;
        return ['discount' => $amount, 'vat_exemption' => $vatTotal / 100.0, 'items' => $plans, 'beneficiary' => $beneficiary];
    }

    public static function netItem(array $item): float
    {
        return max(0, DiscountPolicy::cents((float) ($item['subtotal'] ?? 0))
            - DiscountPolicy::cents((float) ($item['discount_applied'] ?? 0))
            - DiscountPolicy::cents((float) ($item['vat_exemption_amount'] ?? 0))) / 100;
    }
}
