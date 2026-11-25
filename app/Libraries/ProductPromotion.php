<?php

namespace App\Libraries;

final class ProductPromotion
{
    /** Legacy single-product-offer helper; automatic product/category quotes use all(). */
    public static function best(array $discounts, array $items, string $today): ?array
    {
        $best = null;
        foreach ($discounts as $discount) {
            if (($discount['status'] ?? '') !== 'active' || ($discount['applies_to'] ?? '') !== 'product'
                || !empty($discount['deleted_at']) || !empty($discount['is_deleted']) || !empty($discount['is_permanently_deleted'])
                || (!empty($discount['start_date']) && $discount['start_date'] > $today)
                || (!empty($discount['end_date']) && $discount['end_date'] < $today)) continue;
            $productId = (int) ($discount['product_id'] ?? 0);
            if ($productId <= 0 || !($rule = DiscountPolicy::fromDiscount($discount))) continue;
            $eligibleCents = 0;
            foreach ($items as $item) {
                if ((int) ($item['product_id'] ?? 0) === $productId) {
                    $eligibleCents += DiscountPolicy::cents((float) $item['subtotal']);
                }
            }
            $amount = DiscountPolicy::amount($eligibleCents / 100, $rule);
            if ($amount <= 0) continue;
            if ($best === null || $amount > $best['amount']
                || ($amount === $best['amount'] && (int) $discount['id'] < (int) $best['discount']['id'])) {
                $best = ['discount' => $discount, 'amount' => $amount, 'eligible_subtotal' => $eligibleCents / 100];
            }
        }
        return $best;
    }

    /** Best product/category offer per item. Category fixed amounts and caps are allocated once. */
    public static function all(array $discounts, array $items, string $today): array
    {
        $offers = [];
        foreach ($discounts as $discount) {
            $scope = $discount['applies_to'] ?? '';
            if (!in_array($scope, ['product','category'], true) || ($discount['status'] ?? '') !== 'active'
                || !empty($discount['deleted_at']) || !empty($discount['is_deleted']) || !empty($discount['is_permanently_deleted'])
                || (!empty($discount['start_date']) && $discount['start_date'] > $today)
                || (!empty($discount['end_date']) && $discount['end_date'] < $today)
                || (int)($discount[$scope.'_id'] ?? 0) <= 0 || !($rule = DiscountPolicy::fromDiscount($discount))) continue;
            $bases = [];
            foreach ($items as $item) {
                if ((int)($item[$scope.'_id'] ?? 0) !== (int)$discount[$scope.'_id']) continue;
                $id = (int)$item['product_id'];
                $bases[$id] = ($bases[$id] ?? 0) + DiscountPolicy::cents((float)$item['subtotal']);
            }
            ksort($bases, SORT_NUMERIC);
            $bases = array_map(static fn($cents)=>$cents/100, $bases);
            $eligible = array_sum($bases);
            $amount = DiscountPolicy::amount($eligible, $rule);
            foreach (DiscountAllocator::allocate($bases, $amount) as $id=>$share) {
                if ($share <= 0) continue;
                $offer = ['discount'=>$discount, 'product_id'=>$id, 'amount'=>$share, 'eligible_subtotal'=>$eligible];
                if (!isset($offers[$id]) || $share > $offers[$id]['amount']
                    || ($share === $offers[$id]['amount'] && (int)$discount['id'] < (int)$offers[$id]['discount']['id'])) $offers[$id] = $offer;
            }
        }
        ksort($offers, SORT_NUMERIC);
        return array_values($offers);
    }

    public static function total(array $offers): float
    {
        return array_sum(array_map(static fn($offer) => DiscountPolicy::cents($offer['amount']), $offers)) / 100;
    }

    public static function productId(array $offer): int
    {
        return (int)($offer['product_id'] ?? $offer['discount']['product_id']);
    }

    public static function names(array $offers): string
    {
        return implode(' + ', array_unique(array_column(self::snapshot($offers), 'name')));
    }

    public static function snapshot(array $offers): array
    {
        return array_map(static fn($offer) => ['id'=>(int)$offer['discount']['id'],
            'product_id'=>self::productId($offer), 'name'=>$offer['discount']['discount_name'],
            'scope'=>$offer['discount']['applies_to'], 'category_id'=>$offer['discount']['applies_to'] === 'category' ? (int)$offer['discount']['category_id'] : null,
            'amount'=>$offer['amount']], $offers);
    }

    /** Validate every displayed offer, not just the combined savings. Older single-promo clients remain supported. */
    public static function matchesPreview(array $input, array $offers): bool
    {
        $amount = $input['auto_promo_amount'] ?? null;
        if (!is_scalar($amount) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string)$amount)
            || DiscountPolicy::cents((float)$amount) !== DiscountPolicy::cents(self::total($offers))) return false;
        if (!array_key_exists('auto_promo_snapshot', $input)) {
            $id = $input['auto_promo_id'] ?? null;
            return count($offers) <= 1 && is_scalar($id) && (string)$id === (string)($offers[0]['discount']['id'] ?? '');
        }
        if (!is_string($input['auto_promo_snapshot']) || strlen($input['auto_promo_snapshot']) > 100000) return false;
        $preview = json_decode($input['auto_promo_snapshot'], true);
        if (!is_array($preview) || !array_is_list($preview) || count($preview) !== count($offers)) return false;
        foreach ($offers as $index=>$offer) {
            $row = $preview[$index];
            if (!is_array($row) || ($row['id'] ?? null) !== (int)$offer['discount']['id']
                || ($row['product_id'] ?? null) !== self::productId($offer)
                || ($row['amount_cents'] ?? null) !== DiscountPolicy::cents($offer['amount'])) return false;
        }
        return true;
    }
}
